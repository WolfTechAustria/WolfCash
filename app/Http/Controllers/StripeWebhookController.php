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

class StripeWebhookController extends Controller
{
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
        /*
         * Bei checkout.session.completed kann z. B.
         * bei verzögerten Zahlungsmethoden der Status
         * noch nicht "paid" sein.
         */
        if (($session->payment_status ?? null) !== 'paid') {
            return;
        }

        $selfOrderId =
            $session->metadata->self_order_id
            ?? $session->client_reference_id
            ?? null;

        if (! $selfOrderId) {
            Log::warning(
                'Stripe checkout without self_order_id',
                [
                    'checkout_session' =>
                        $session->id ?? null,
                ]
            );

            return;
        }

        DB::transaction(function () use (
            $session,
            $selfOrderId
        ): void {
            $selfOrder = SelfOrder::query()
                ->whereKey((int) $selfOrderId)
                ->lockForUpdate()
                ->first();

            if (! $selfOrder) {
                Log::warning(
                    'SelfOrder for Stripe checkout not found',
                    [
                        'self_order_id' =>
                            $selfOrderId,

                        'checkout_session' =>
                            $session->id ?? null,
                    ]
                );

                return;
            }

            /*
             * Idempotenz:
             *
             * Stripe kann dasselbe Event mehrfach senden.
             * Bereits bezahlte oder submitted Bestellungen
             * werden nicht ein zweites Mal verarbeitet.
             */
            if (
                in_array(
                    $selfOrder->status,
                    [
                        SelfOrder::STATUS_PAID,
                        SelfOrder::STATUS_SUBMITTED,
                    ],
                    true
                )
            ) {
                return;
            }

            /*
             * Stripe-Checkout-Session muss exakt
             * zur SelfOrder gehören.
             */
            if (
                $selfOrder->provider_payment_id
                !== ($session->id ?? null)
            ) {
                Log::warning(
                    'Stripe checkout session mismatch',
                    [
                        'self_order_id' =>
                            $selfOrder->id,

                        'expected' =>
                            $selfOrder->provider_payment_id,

                        'received' =>
                            $session->id ?? null,
                    ]
                );

                return;
            }

            /*
             * Betrag nochmals prüfen.
             *
             * Stripe liefert amount_total in Cent.
             */
            $stripeAmount =
                ((int) ($session->amount_total ?? 0))
                / 100;

            if (
                abs(
                    $stripeAmount
                    - (float) $selfOrder->amount
                ) > 0.009
            ) {
                Log::error(
                    'Stripe amount mismatch',
                    [
                        'self_order_id' =>
                            $selfOrder->id,

                        'expected' =>
                            $selfOrder->amount,

                        'received' =>
                            $stripeAmount,
                    ]
                );

                return;
            }

            /*
             * Währung ebenfalls prüfen.
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
                Log::error(
                    'Stripe currency mismatch',
                    [
                        'self_order_id' =>
                            $selfOrder->id,

                        'expected' =>
                            $selfOrder->currency,

                        'received' =>
                            $stripeCurrency,
                    ]
                );

                return;
            }

            /*
             * Erst jetzt gilt die SelfOrder
             * serverseitig als bezahlt.
             *
             * Noch KEINE normale Order und
             * KEIN Produktionsbon.
             */
            $selfOrder->update([
                'status' =>
                    SelfOrder::STATUS_PAID,

                'paid_at' =>
                    now(),
            ]);
        });
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
