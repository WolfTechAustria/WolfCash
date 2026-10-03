<?php

namespace Tests\Fakes;

use App\Services\Stripe\TerminalGateway;
use RuntimeException;
use Stripe\PaymentIntent;

/**
 * Bildet die für Tap to Pay genutzten Stripe-Aufrufe im Speicher
 * nach. authorize() simuliert das Einlesen der Karte in der App.
 */
class FakeTerminalGateway implements TerminalGateway
{
    /** @var array<string, array<string, mixed>> */
    public array $intents = [];

    /** @var list<string> */
    public array $captured = [];

    /** @var list<string> */
    public array $canceled = [];

    public bool $failCreate = false;

    public bool $failCapture = false;

    public function createPaymentIntent(
        int $amountInCents,
        string $currency,
        array $metadata,
        string $description,
        string $idempotencyKey,
    ): PaymentIntent {
        if ($this->failCreate) {
            throw new RuntimeException('Stripe nicht erreichbar.');
        }

        $id = 'pi_test_'.(count($this->intents) + 1);

        $this->intents[$id] = [
            'id' => $id,
            'object' => 'payment_intent',
            'amount' => $amountInCents,
            'currency' => strtolower($currency),
            'metadata' => $metadata,
            'status' => PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
            'client_secret' => $id.'_secret_test',
            'capture_method' => 'manual',
        ];

        return $this->intent($id);
    }

    public function retrievePaymentIntent(string $id): PaymentIntent
    {
        return $this->intent($id);
    }

    public function capturePaymentIntent(string $id): PaymentIntent
    {
        if ($this->failCapture) {
            throw new RuntimeException('Capture fehlgeschlagen.');
        }

        $this->assertStatus($id, PaymentIntent::STATUS_REQUIRES_CAPTURE);

        $this->intents[$id]['status'] = PaymentIntent::STATUS_SUCCEEDED;
        $this->captured[] = $id;

        return $this->intent($id);
    }

    public function cancelPaymentIntent(string $id): PaymentIntent
    {
        if (in_array($this->intents[$id]['status'], [
            PaymentIntent::STATUS_SUCCEEDED,
            PaymentIntent::STATUS_CANCELED,
        ], true)) {
            throw new RuntimeException('PaymentIntent kann nicht storniert werden.');
        }

        $this->intents[$id]['status'] = PaymentIntent::STATUS_CANCELED;
        $this->canceled[] = $id;

        return $this->intent($id);
    }

    /**
     * Karte in der App erfolgreich eingelesen.
     */
    public function authorize(string $id): void
    {
        $this->assertStatus($id, PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD);

        $this->intents[$id]['status'] = PaymentIntent::STATUS_REQUIRES_CAPTURE;
    }

    public function status(string $id): string
    {
        return $this->intents[$id]['status'];
    }

    private function intent(string $id): PaymentIntent
    {
        return PaymentIntent::constructFrom($this->intents[$id]);
    }

    private function assertStatus(string $id, string $status): void
    {
        if ($this->intents[$id]['status'] !== $status) {
            throw new RuntimeException(
                "PaymentIntent {$id} ist {$this->intents[$id]['status']}, erwartet {$status}."
            );
        }
    }
}
