<?php

namespace App\Services;

use App\Models\SelfOrder;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

class SelfOrderPaymentService
{
    public function createCheckoutSession(
        SelfOrder $selfOrder
    ): Session {
        $selfOrder->loadMissing([
            'items',
            'table',
        ]);

        if (
            $selfOrder->status
            !== SelfOrder::STATUS_AWAITING_PAYMENT
        ) {
            throw new \RuntimeException(
                'Diese Bestellung kann nicht mehr bezahlt werden.'
            );
        }

        if (
            $selfOrder->expires_at !== null
            && $selfOrder->expires_at->isPast()
        ) {
            throw new \RuntimeException(
                'Diese Bestellung ist bereits abgelaufen.'
            );
        }

        if ($selfOrder->items->isEmpty()) {
            throw new \RuntimeException(
                'Die Bestellung enthält keine Positionen.'
            );
        }

        $stripe = new StripeClient(
            config('services.stripe.secret')
        );

        $lineItems = [];

        foreach ($selfOrder->items as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' =>
                        strtolower(
                            $selfOrder->currency
                        ),

                    'product_data' => [
                        'name' =>
                            $item->name,
                    ],

                    /*
                     * Stripe erwartet Cent.
                     */
                    'unit_amount' =>
                        (int) round(
                            (float) $item->unit_price
                            * 100
                        ),
                ],

                'quantity' =>
                    (int) $item->quantity,
            ];
        }

        $successUrl = route(
            'self-order.payment.success',
            [
                'selfOrder' =>
                    $selfOrder->id,
            ],
            false
        );

        $cancelUrl = route(
            'self-order.payment.cancel',
            [
                'selfOrder' =>
                    $selfOrder->id,
            ],
            false
        );

        /*
         * Stripe braucht absolute URLs.
         * Wegen unseres Reverse-Proxy-Setups
         * bauen wir diese bewusst aus
         * SELF_ORDER_BASE_URL.
         */
        $baseUrl = rtrim(
            config('self_order.base_url'),
            '/'
        );

        $session = $stripe
            ->checkout
            ->sessions
            ->create([
                'mode' => 'payment',

                'line_items' =>
                    $lineItems,

                /*
                 * Damit Stripe die aktuell für
                 * euer Konto/Region möglichen
                 * Zahlungsmethoden bestimmen kann,
                 * setzen wir hier zunächst keine
                 * feste payment_method_types-Liste.
                 */

                'success_url' =>
                    $baseUrl
                    .$successUrl
                    .'?session_id={CHECKOUT_SESSION_ID}',

                'cancel_url' =>
                    $baseUrl
                    .$cancelUrl,

                /*
                 * Sehr wichtig für die Zuordnung.
                 */
                'client_reference_id' =>
                    (string) $selfOrder->id,

                'metadata' => [
                    'self_order_id' =>
                        (string) $selfOrder->id,

                    'table_id' =>
                        (string) $selfOrder->table_id,
                ],
            ]);

        $selfOrder->update([
            'payment_provider' =>
                'stripe',

            /*
             * Hier speichern wir zunächst
             * die Checkout-Session-ID.
             */
            'provider_payment_id' =>
                $session->id,

            'status' =>
                SelfOrder::STATUS_PAYMENT_PROCESSING,
        ]);

        return $session;
    }
}
