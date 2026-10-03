<?php

namespace App\Services;

use App\Exceptions\CardPaymentException;
use App\Exceptions\PaymentAmountMismatchException;
use App\Http\Middleware\EnsureFloorDevice;
use App\Models\CardPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Stripe\TerminalGateway;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stripe\PaymentIntent;
use Throwable;

/**
 * Kartenzahlung über Stripe Terminal (Tap to Pay):
 *
 * 1. start(): Betrag und Positionen werden serverseitig festgehalten,
 *    Stripe erstellt einen PaymentIntent, der nur reserviert.
 * 2. Die App liest die Karte ein (Status requires_capture).
 * 3. complete(): Der Server prüft den PaymentIntent selbst bei Stripe,
 *    verbucht die Positionen und bucht erst danach ab – beides in
 *    einer Transaktion.
 *
 * Schlägt etwas fehl, wird die Reservierung storniert. Es wird
 * nichts abgebucht und die Positionen bleiben offen (z. B. für Bar).
 */
class CardPaymentService
{
    /*
     * Nicht abgeschlossene Reservierungen werden danach storniert.
     */
    public const EXPIRES_AFTER_MINUTES = 15;

    public function __construct(
        private readonly TerminalGateway $gateway,
        private readonly PaymentService $paymentService,
    ) {}

    /**
     * @param  array<int|string, int>  $selection  nur bei MODE_SELECTED
     * @param  array{name: ?string, address: ?string, vat_id: ?string}|null  $invoiceRecipient
     * @return array{card_payment: CardPayment, client_secret: string}
     */
    public function start(
        Order $order,
        string $mode,
        array $selection,
        float $amount,
        ?array $invoiceRecipient = null,
        string $currency = 'EUR',
    ): array {
        $this->abortOpenFor(
            $order,
            'Durch eine neue Kartenzahlung ersetzt.'
        );

        $cardPayment = CardPayment::create([
            'order_id' => $order->id,
            'mode' => $mode,
            'selection' => $mode === CardPayment::MODE_SELECTED
                ? $selection
                : null,
            'invoice_recipient' => $invoiceRecipient,
            'amount' => round($amount, 2),
            'currency' => $currency,
            'status' => CardPayment::STATUS_PENDING,
            'device_id' => EnsureFloorDevice::resolveDevice(request())?->id,
            'user_id' => auth()->id(),
        ]);

        try {
            $intent = $this->gateway->createPaymentIntent(
                $cardPayment->amountInCents(),
                $currency,
                [
                    'card_payment_id' => (string) $cardPayment->id,
                    'order_id' => (string) $order->id,
                ],
                sprintf(
                    'WolfCash Tisch %s, Bestellung #%d',
                    $order->table?->number ?? '–',
                    $order->id
                ),
                'card-payment-'.Str::uuid(),
            );
        } catch (Throwable $exception) {
            report($exception);

            $cardPayment->update([
                'status' => CardPayment::STATUS_CANCELED,
                'failure_message' => 'Stripe nicht erreichbar.',
            ]);

            throw new CardPaymentException(
                'Die Kartenzahlung konnte nicht gestartet werden. Bitte erneut versuchen oder bar kassieren.',
                previous: $exception
            );
        }

        $cardPayment->update([
            'stripe_payment_intent_id' => $intent->id,
        ]);

        return [
            'card_payment' => $cardPayment,
            'client_secret' => (string) $intent->client_secret,
        ];
    }

    /**
     * Verbucht eine in der App autorisierte Kartenzahlung und bucht
     * sie bei Stripe ab. Der Status kommt direkt von Stripe, nicht
     * aus der App.
     */
    public function complete(CardPayment $cardPayment): Payment
    {
        $cardPayment->refresh();

        if (
            $cardPayment->status === CardPayment::STATUS_COMPLETED
            && $cardPayment->payment
        ) {
            return $cardPayment->payment;
        }

        if (
            ! $cardPayment->isPending()
            || ! $cardPayment->stripe_payment_intent_id
        ) {
            throw new CardPaymentException(
                'Diese Kartenzahlung ist nicht mehr aktiv.'
            );
        }

        try {
            $intent = $this->gateway->retrievePaymentIntent(
                $cardPayment->stripe_payment_intent_id
            );
        } catch (Throwable $exception) {
            report($exception);

            $this->abort(
                $cardPayment,
                'Status bei Stripe nicht abrufbar.'
            );

            throw new CardPaymentException(
                'Der Zahlungsstatus konnte nicht bei Stripe geprüft werden. Die Zahlung wurde storniert.',
                previous: $exception
            );
        }

        $this->assertIntentMatches($cardPayment, $intent);

        $alreadyCaptured =
            $intent->status === PaymentIntent::STATUS_SUCCEEDED;

        if (
            ! $alreadyCaptured
            && $intent->status !== PaymentIntent::STATUS_REQUIRES_CAPTURE
        ) {
            $this->abort(
                $cardPayment,
                'Karte nicht autorisiert (Stripe-Status '.$intent->status.').'
            );

            throw new CardPaymentException(
                'Die Karte wurde nicht autorisiert. Es wurde nichts abgebucht.'
            );
        }

        try {
            return DB::transaction(function () use (
                $cardPayment,
                $alreadyCaptured
            ): Payment {
                $locked = CardPayment::query()
                    ->whereKey($cardPayment->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->isPending()) {
                    throw new CardPaymentException(
                        'Diese Kartenzahlung wurde bereits verarbeitet.'
                    );
                }

                $payment = $this->book($locked);

                $locked->update([
                    'status' => CardPayment::STATUS_COMPLETED,
                    'payment_id' => $payment->id,
                    'completed_at' => now(),
                    'failure_message' => null,
                ]);

                /*
                 * Zuletzt abbuchen: Scheitert die Abbuchung, wird
                 * die Buchung zurückgerollt.
                 */
                if (! $alreadyCaptured) {
                    $this->gateway->capturePaymentIntent(
                        $locked->stripe_payment_intent_id
                    );
                }

                return $payment;
            });
        } catch (CardPaymentException $exception) {
            throw $exception;
        } catch (PaymentAmountMismatchException $exception) {
            $message = 'Die offenen Positionen haben sich während der Kartenzahlung geändert. Die Zahlung wurde storniert, es wurde nichts abgebucht.';

            $this->abort($cardPayment, $message);

            throw new CardPaymentException($message, previous: $exception);
        } catch (Throwable $exception) {
            report($exception);

            $this->abort(
                $cardPayment,
                'Verbuchen fehlgeschlagen: '.$exception->getMessage()
            );

            throw new CardPaymentException(
                'Die Kartenzahlung konnte nicht verbucht werden. Es wurde nichts abgebucht.',
                previous: $exception
            );
        }
    }

    /**
     * Storniert die Reservierung bei Stripe und markiert die
     * Kartenzahlung als abgebrochen.
     */
    public function abort(CardPayment $cardPayment, string $reason): void
    {
        $cardPayment->refresh();

        if (! $cardPayment->isPending()) {
            return;
        }

        if ($cardPayment->stripe_payment_intent_id) {
            try {
                $this->gateway->cancelPaymentIntent(
                    $cardPayment->stripe_payment_intent_id
                );
            } catch (Throwable $exception) {
                if (! $this->handleFailedCancel($cardPayment, $exception)) {
                    return;
                }
            }
        }

        $cardPayment->update([
            'status' => CardPayment::STATUS_CANCELED,
            'failure_message' => mb_substr($reason, 0, 255),
        ]);
    }

    /**
     * Storniert offene Kartenzahlungen dieser Bestellung.
     */
    public function abortOpenFor(Order $order, string $reason): void
    {
        CardPayment::query()
            ->where('order_id', $order->id)
            ->where('status', CardPayment::STATUS_PENDING)
            ->get()
            ->each(fn (CardPayment $cardPayment) => $this->abort($cardPayment, $reason));
    }

    /**
     * Storniert Reservierungen, die nie abgeschlossen wurden
     * (App abgestürzt, WebView neu geladen, …).
     */
    public function expireStale(): int
    {
        $stale = CardPayment::query()
            ->where('status', CardPayment::STATUS_PENDING)
            ->where('created_at', '<', now()->subMinutes(self::EXPIRES_AFTER_MINUTES))
            ->get();

        foreach ($stale as $cardPayment) {
            $this->abort($cardPayment, 'Zeitüberschreitung.');
        }

        return $stale->count();
    }

    private function book(CardPayment $cardPayment): Payment
    {
        $order = Order::query()->findOrFail($cardPayment->order_id);

        $payment = $cardPayment->mode === CardPayment::MODE_SELECTED
            ? $this->paymentService->paySelection(
                $order,
                $cardPayment->selection ?? [],
                Payment::CARD,
                $cardPayment->invoice_recipient,
                (float) $cardPayment->amount
            )
            : $this->paymentService->payRemaining(
                $order,
                Payment::CARD,
                $cardPayment->invoice_recipient,
                (float) $cardPayment->amount
            );

        if (! $payment) {
            throw new PaymentAmountMismatchException(
                (float) $cardPayment->amount,
                0.0
            );
        }

        return $payment;
    }

    private function assertIntentMatches(
        CardPayment $cardPayment,
        PaymentIntent $intent
    ): void {
        $matches =
            (string) ($intent->metadata['card_payment_id'] ?? '') === (string) $cardPayment->id
            && (int) $intent->amount === $cardPayment->amountInCents()
            && strtolower((string) $intent->currency) === strtolower($cardPayment->currency);

        if ($matches) {
            return;
        }

        Log::warning('Stripe PaymentIntent passt nicht zur Kartenzahlung.', [
            'card_payment_id' => $cardPayment->id,
            'payment_intent_id' => $intent->id,
        ]);

        $this->abort($cardPayment, 'PaymentIntent passt nicht zur Kartenzahlung.');

        throw new CardPaymentException(
            'Die Kartenzahlung passt nicht zu dieser Bestellung und wurde storniert.'
        );
    }

    /**
     * Liefert true, wenn die Kartenzahlung als storniert markiert
     * werden darf (PaymentIntent ist bereits storniert).
     */
    private function handleFailedCancel(
        CardPayment $cardPayment,
        Throwable $exception
    ): bool {
        try {
            $intent = $this->gateway->retrievePaymentIntent(
                $cardPayment->stripe_payment_intent_id
            );
        } catch (Throwable) {
            $intent = null;
        }

        if ($intent?->status === PaymentIntent::STATUS_CANCELED) {
            return true;
        }

        if ($intent?->status === PaymentIntent::STATUS_SUCCEEDED) {
            Log::critical('Kartenzahlung bei Stripe abgebucht, aber nicht verbucht.', [
                'card_payment_id' => $cardPayment->id,
                'payment_intent_id' => $intent->id,
                'amount' => (float) $cardPayment->amount,
            ]);

            $cardPayment->update([
                'status' => CardPayment::STATUS_REQUIRES_ATTENTION,
                'failure_message' => 'Bei Stripe abgebucht, aber nicht verbucht.',
            ]);

            return false;
        }

        /*
         * Stripe nicht erreichbar: bleibt offen, der nächste
         * Ablauf-Lauf (expireStale) versucht es erneut.
         */
        report($exception);

        return false;
    }
}
