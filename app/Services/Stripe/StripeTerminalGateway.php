<?php

namespace App\Services\Stripe;

use Stripe\PaymentIntent;
use Stripe\StripeClient;

class StripeTerminalGateway implements TerminalGateway
{
    private ?StripeClient $client = null;

    public function createPaymentIntent(
        int $amountInCents,
        string $currency,
        array $metadata,
        string $description,
        string $idempotencyKey,
    ): PaymentIntent {
        return $this->client()->paymentIntents->create(
            [
                'amount' => $amountInCents,
                'currency' => strtolower($currency),
                'payment_method_types' => ['card_present'],
                'capture_method' => 'manual',
                'description' => $description,
                'metadata' => $metadata,
            ],
            [
                'idempotency_key' => $idempotencyKey,
            ]
        );
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return $this->client()->paymentIntents->retrieve($id);
    }

    public function capturePaymentIntent(string $id): PaymentIntent
    {
        return $this->client()->paymentIntents->capture(
            $id,
            [],
            ['idempotency_key' => 'capture-'.$id]
        );
    }

    public function cancelPaymentIntent(string $id): PaymentIntent
    {
        return $this->client()->paymentIntents->cancel($id);
    }

    private function client(): StripeClient
    {
        return $this->client ??= new StripeClient(
            config('services.stripe.secret')
        );
    }
}
