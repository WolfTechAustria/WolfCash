<?php

namespace App\Http\Controllers;

use App\Models\SelfOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;
use App\Services\SelfOrderSubmissionService;
use Stripe\StripeClient;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly SelfOrderSubmissionService $submissionService)
    {

    }

    private function detectPaymentMethod(
        object $session
    ): array {
        $paymentIntentId =
            $session->payment_intent ?? null;

        if (! $paymentIntentId) {
            return [
                'payment_intent_id' => null,
                'type' => null,
                'wallet' => null,
            ];
        }

        $stripe = new StripeClient(
            config('services.stripe.secret')
        );

        $paymentIntent =
            $stripe->paymentIntents->retrieve(
                $paymentIntentId,
                [
                    'expand' => [
                        'latest_charge',
                    ],
                ]
            );

        $charge =
            $paymentIntent->latest_charge;

        $details =
            $charge?->payment_method_details;

        $type =
            $details?->type;

        $wallet = null;

        if (
            $type === 'card'
            && $details?->card?->wallet
        ) {
            $wallet =
                $details->card->wallet->type
                ?? null;
        }

        return [
            'payment_intent_id' =>
                $paymentIntent->id,

            'type' =>
                $type,

            'wallet' =>
                $wallet,
        ];
    }

    public function handle(Request $request): Response
    {
        $payload = $request->getContent();

        $signature = $request->header(
            'Stripe-Signature'
        );

        $webhookSecret = config(
            'services.stripe.webhook_secret'
        );

        if (! $signature || ! $webhookSecret) {
            return response(
                'Webhook configuration missing.',
                400
            );
        }

        try {
            /*
             * Sehr wichtig:
             * Stripe-Signatur gegen den ORIGINALEN
             * Request-Body prüfen.
             */
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret
            );
        } catch (UnexpectedValueException) {
            return response(
                'Invalid payload.',
                400
            );
        } catch (SignatureVerificationException) {
            return response(
                'Invalid signature.',
                400
            );
        }

        switch ($event->type) {

            case 'checkout.session.completed':
                $this->handleCheckoutCompleted(
                    $event->data->object
                );

                break;


            case 'checkout.session.async_payment_succeeded':
                $this->handleCheckoutCompleted(
                    $event->data->object
                );

                break;


            case 'checkout.session.async_payment_failed':
                $this->handleCheckoutFailed(
                    $event->data->object
                );

                break;
        }

        return response('OK', 200);
    }


    private function handleCheckoutCompleted(
        object $session
    ): void {
        if (
            ($session->payment_status ?? null)
            !== 'paid'
        ) {
            return;
        }

        $selfOrderId =
            $session->metadata->self_order_id
            ?? $session->client_reference_id
            ?? null;

        if (! $selfOrderId) {
            return;
        }

        $selfOrder = DB::transaction(
            function () use (
                $session,
                $selfOrderId
            ): ?SelfOrder {

                $selfOrder = SelfOrder::query()
                    ->whereKey(
                        (int) $selfOrderId
                    )
                    ->lockForUpdate()
                    ->first();

                if (! $selfOrder) {
                    return null;
                }

                /*
                 * Schon komplett verarbeitet.
                 */
                if (
                    $selfOrder->status
                    === SelfOrder::STATUS_SUBMITTED
                ) {
                    return $selfOrder;
                }



                /*
                 * Richtige Stripe Checkout Session?
                 */
                if (
                    $selfOrder->provider_payment_id
                    !== ($session->id ?? null)
                ) {
                    throw new \RuntimeException(
                        'Stripe Checkout Session stimmt '
                        .'nicht mit der SelfOrder überein.'
                    );
                }

                /*
                 * Betrag in Cent vergleichen.
                 */
                $stripeAmountCents =
                    (int) (
                        $session->amount_total
                        ?? 0
                    );

                $selfOrderAmountCents =
                    (int) round(
                        (float) $selfOrder->amount
                        * 100
                    );

                if (
                    $stripeAmountCents
                    !== $selfOrderAmountCents
                ) {
                    throw new \RuntimeException(
                        'Stripe-Zahlungsbetrag stimmt nicht.'
                    );
                }

                /*
                 * Währung prüfen.
                 */
                $stripeCurrency =
                    strtoupper(
                        (string) (
                            $session->currency
                            ?? ''
                        )
                    );

                if (
                    $stripeCurrency
                    !== strtoupper(
                        $selfOrder->currency
                    )
                ) {
                    throw new \RuntimeException(
                        'Stripe-Währung stimmt nicht.'
                    );
                }

                $paymentMethod =
                    $this->detectPaymentMethod(
                        $session
                    );

                if (
                    $selfOrder->status
                    !== SelfOrder::STATUS_SUBMITTED
                ) {
                    $selfOrder->update([
                        'provider_payment_intent_id' =>
                            $paymentMethod['payment_intent_id'],

                        'payment_method_type' =>
                            $paymentMethod['type'],

                        'payment_wallet' =>
                            $paymentMethod['wallet'],
                    ]);
                }


                /*
                 * Zahlung ist serverseitig bestätigt.
                 */
                if (
                    $selfOrder->status
                    !== SelfOrder::STATUS_PAID
                ) {
                    $selfOrder->update([
                        'status' =>
                            SelfOrder::STATUS_PAID,

                        'paid_at' =>
                            now(),

                        'provider_payment_intent_id' =>
                            $paymentMethod['payment_intent_id'],

                        'payment_method_type' =>
                            $paymentMethod['type'],

                        'payment_wallet' =>
                            $paymentMethod['wallet'],
                    ]);
                }

                return $selfOrder->fresh();
            }
        );

        if (! $selfOrder) {
            return;
        }

        /*
         * JETZT darf die Bestellung boniert werden.
         *
         * Fehler hier bewusst NICHT verschlucken:
         * Stripe bekommt dann HTTP 500 und kann
         * den Webhook erneut zustellen.
         */
        $this->submissionService->submit(
            $selfOrder
        );
    }


    private function handleCheckoutFailed(
        object $session
    ): void {
        $selfOrderId =
            $session->metadata->self_order_id
            ?? $session->client_reference_id
            ?? null;

        if (! $selfOrderId) {
            return;
        }

        SelfOrder::query()
            ->whereKey((int) $selfOrderId)
            ->where(
                'status',
                SelfOrder::STATUS_PAYMENT_PROCESSING
            )
            ->update([
                'status' =>
                    SelfOrder::STATUS_AWAITING_PAYMENT,
            ]);
    }
}
