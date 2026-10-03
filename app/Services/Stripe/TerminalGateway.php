<?php

namespace App\Services\Stripe;

use Stripe\PaymentIntent;

/**
 * Stripe-Aufrufe für Kartenzahlungen am Terminal (Tap to Pay).
 * Als eigene Schnittstelle, damit Tests ohne Stripe laufen.
 */
interface TerminalGateway
{
    /**
     * Erstellt einen PaymentIntent, der nur reserviert
     * (capture_method manual) und erst nach dem Verbuchen
     * abgebucht wird.
     *
     * @param  array<string, string>  $metadata
     */
    public function createPaymentIntent(
        int $amountInCents,
        string $currency,
        array $metadata,
        string $description,
        string $idempotencyKey,
    ): PaymentIntent;

    public function retrievePaymentIntent(string $id): PaymentIntent;

    public function capturePaymentIntent(string $id): PaymentIntent;

    public function cancelPaymentIntent(string $id): PaymentIntent;
}
