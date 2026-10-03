<?php

namespace Tests\Feature;

use App\Livewire\Pos\Checkout;
use App\Models\CardPayment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\Table;
use App\Services\CardPaymentService;
use App\Services\Stripe\TerminalGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Stripe\PaymentIntent;
use Tests\Fakes\FakeTerminalGateway;
use Tests\TestCase;

class CardPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private FakeTerminalGateway $stripe;

    private Table $table;

    private Order $order;

    private OrderItem $beer;

    private OrderItem $soda;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::putValue(Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED, false);

        $this->stripe = new FakeTerminalGateway;
        $this->app->instance(TerminalGateway::class, $this->stripe);

        $this->table = Table::create(['number' => '1', 'name' => 'Tisch 1']);
        $group = ProductGroup::create(['name' => 'Getränke']);
        $category = ProductCategory::create(['product_group_id' => $group->id, 'name' => 'Getränke']);

        $product = fn (string $name, float $price) => Product::create([
            'name' => $name,
            'price' => $price,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);

        $this->order = Order::create(['table_id' => $this->table->id, 'status' => Order::STATUS_OPEN]);

        $this->beer = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product('Bier', 5)->id,
            'quantity' => 2,
            'price' => 5,
        ]);

        $this->soda = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product('Soda', 3.5)->id,
            'quantity' => 1,
            'price' => 3.5,
        ]);
    }

    private function checkout()
    {
        return Livewire::test(Checkout::class, ['table' => $this->table]);
    }

    private function onlyCardPayment(): CardPayment
    {
        $this->assertSame(1, CardPayment::count());

        return CardPayment::firstOrFail();
    }

    public function test_start_reserves_server_side_amount_and_hands_client_secret_to_app(): void
    {
        $this->checkout()
            ->call('startRemainingCardPayment')
            ->assertSet('pendingCardPaymentId', fn ($id) => $id !== null)
            ->assertDispatched(
                'start-native-card-payment',
                fn (string $name, array $params) => $params['clientSecret'] === 'pi_test_1_secret_test'
                    && $params['amount'] === 13.5
                    && $params['currency'] === 'EUR'
            );

        $cardPayment = $this->onlyCardPayment();

        $this->assertSame(CardPayment::STATUS_PENDING, $cardPayment->status);
        $this->assertSame('pi_test_1', $cardPayment->stripe_payment_intent_id);
        $this->assertSame(1350, $this->stripe->intents['pi_test_1']['amount']);
        $this->assertSame((string) $cardPayment->id, $this->stripe->intents['pi_test_1']['metadata']['card_payment_id']);
    }

    public function test_authorized_payment_is_booked_and_then_captured(): void
    {
        $component = $this->checkout()->call('startRemainingCardPayment');
        $cardPayment = $this->onlyCardPayment();

        $this->stripe->authorize('pi_test_1');

        $component
            ->dispatch('native-card-payment-result', success: true, cardPaymentId: $cardPayment->id)
            ->assertHasNoErrors()
            ->assertSet('paymentFinished', true)
            ->assertSet('pendingCardPaymentId', null);

        $payment = Payment::sole();

        $this->assertSame(Payment::CARD, $payment->payment_method);
        $this->assertEquals(13.5, (float) $payment->amount);
        $this->assertSame(['pi_test_1'], $this->stripe->captured);
        $this->assertSame(CardPayment::STATUS_COMPLETED, $cardPayment->fresh()->status);
        $this->assertSame($payment->id, $cardPayment->fresh()->payment_id);
        $this->assertSame(Order::STATUS_PAID, $this->order->fresh()->status);
    }

    public function test_selected_card_payment_pays_only_selection(): void
    {
        $component = $this->checkout()
            ->call('addToPayment', $this->beer->id)
            ->call('startSelectedCardPayment');

        $cardPayment = $this->onlyCardPayment();
        $this->assertEquals(5.0, (float) $cardPayment->amount);

        $this->stripe->authorize('pi_test_1');

        $component->dispatch('native-card-payment-result', success: true, cardPaymentId: $cardPayment->id);

        $this->assertEquals(5.0, (float) Payment::sole()->amount);
        $this->assertSame(Order::STATUS_OPEN, $this->order->fresh()->status);
        $this->assertNull($this->soda->fresh()->paid_at);
    }

    public function test_app_success_is_not_trusted_without_stripe_authorization(): void
    {
        $component = $this->checkout()->call('startRemainingCardPayment');
        $cardPayment = $this->onlyCardPayment();

        // Karte wurde nie eingelesen, die App meldet trotzdem Erfolg.
        $component
            ->dispatch('native-card-payment-result', success: true, cardPaymentId: $cardPayment->id)
            ->assertHasErrors('payment');

        $this->assertSame(0, Payment::count());
        $this->assertSame([], $this->stripe->captured);
        $this->assertSame(PaymentIntent::STATUS_CANCELED, $this->stripe->status('pi_test_1'));
        $this->assertSame(CardPayment::STATUS_CANCELED, $cardPayment->fresh()->status);
    }

    public function test_failed_card_payment_cancels_reservation_and_cash_remains_possible(): void
    {
        $component = $this->checkout()->call('startRemainingCardPayment');
        $cardPayment = $this->onlyCardPayment();

        $component
            ->dispatch('native-card-payment-result', success: false, cardPaymentId: $cardPayment->id, error: 'Karte abgelehnt.')
            ->assertHasErrors('payment')
            ->assertSee('Karte abgelehnt. Es wurde nichts abgebucht.');

        $this->assertSame(PaymentIntent::STATUS_CANCELED, $this->stripe->status('pi_test_1'));
        $this->assertSame(CardPayment::STATUS_CANCELED, $cardPayment->fresh()->status);

        $component
            ->call('payOpen', 'cash')
            ->assertSet('paymentFinished', true);

        $this->assertSame(Payment::CASH, Payment::sole()->payment_method);
    }

    public function test_items_paid_in_cash_meanwhile_cancel_the_card_reservation(): void
    {
        $component = $this->checkout()->call('startRemainingCardPayment');
        $cardPayment = $this->onlyCardPayment();

        // Kollege kassiert in der Zwischenzeit das Soda bar.
        $this->checkout()
            ->call('addToPayment', $this->soda->id)
            ->call('paySelected', 'cash');

        $this->stripe->authorize('pi_test_1');

        $component
            ->dispatch('native-card-payment-result', success: true, cardPaymentId: $cardPayment->id)
            ->assertHasErrors('payment');

        $this->assertSame([], $this->stripe->captured);
        $this->assertSame(PaymentIntent::STATUS_CANCELED, $this->stripe->status('pi_test_1'));
        $this->assertSame(CardPayment::STATUS_CANCELED, $cardPayment->fresh()->status);
        $this->assertSame([Payment::CASH], Payment::pluck('payment_method')->all());
        $this->assertNull($this->beer->fresh()->paid_at);
    }

    public function test_failed_capture_rolls_back_booking(): void
    {
        $component = $this->checkout()->call('startRemainingCardPayment');
        $cardPayment = $this->onlyCardPayment();

        $this->stripe->authorize('pi_test_1');
        $this->stripe->failCapture = true;

        $component
            ->dispatch('native-card-payment-result', success: true, cardPaymentId: $cardPayment->id)
            ->assertHasErrors('payment');

        $this->assertSame(0, Payment::count());
        $this->assertNull($this->beer->fresh()->paid_at);
        $this->assertSame(Order::STATUS_OPEN, $this->order->fresh()->status);
        $this->assertSame(PaymentIntent::STATUS_CANCELED, $this->stripe->status('pi_test_1'));
        $this->assertSame(CardPayment::STATUS_CANCELED, $cardPayment->fresh()->status);
    }

    public function test_duplicate_success_result_books_only_once(): void
    {
        $component = $this->checkout()
            ->call('addToPayment', $this->beer->id)
            ->call('startSelectedCardPayment');

        $cardPayment = CardPayment::query()->where('status', CardPayment::STATUS_PENDING)->sole();
        $this->stripe->authorize($cardPayment->stripe_payment_intent_id);

        $component->dispatch('native-card-payment-result', success: true, cardPaymentId: $cardPayment->id);
        $component->dispatch('native-card-payment-result', success: true, cardPaymentId: $cardPayment->id);

        $this->assertSame(1, Payment::count());
        $this->assertCount(1, $this->stripe->captured);
    }

    public function test_new_card_payment_cancels_previous_reservation(): void
    {
        $component = $this->checkout()->call('startRemainingCardPayment');
        $component->call('startRemainingCardPayment');

        $this->assertSame(PaymentIntent::STATUS_CANCELED, $this->stripe->status('pi_test_1'));
        $this->assertSame(1, CardPayment::where('status', CardPayment::STATUS_PENDING)->count());
    }

    public function test_stripe_outage_on_start_shows_error_and_cash_remains_possible(): void
    {
        $this->stripe->failCreate = true;

        $this->checkout()
            ->call('startRemainingCardPayment')
            ->assertHasErrors('payment')
            ->assertNotDispatched('start-native-card-payment')
            ->call('payOpen', 'cash')
            ->assertSet('paymentFinished', true);
    }

    public function test_result_for_other_order_is_rejected(): void
    {
        $otherTable = Table::create(['number' => '2', 'name' => 'Tisch 2']);
        $otherOrder = Order::create(['table_id' => $otherTable->id, 'status' => Order::STATUS_OPEN]);

        $foreign = CardPayment::create([
            'order_id' => $otherOrder->id,
            'mode' => CardPayment::MODE_REMAINING,
            'amount' => 10,
            'currency' => 'EUR',
            'status' => CardPayment::STATUS_PENDING,
        ]);

        $this->checkout()
            ->dispatch('native-card-payment-result', success: true, cardPaymentId: $foreign->id)
            ->assertHasErrors('payment');

        $this->assertSame(CardPayment::STATUS_PENDING, $foreign->fresh()->status);
    }

    public function test_stale_reservations_expire(): void
    {
        $this->checkout()->call('startRemainingCardPayment');
        $cardPayment = $this->onlyCardPayment();

        $this->travel(CardPaymentService::EXPIRES_AFTER_MINUTES + 1)->minutes();

        $this->assertSame(1, app(CardPaymentService::class)->expireStale());
        $this->assertSame(CardPayment::STATUS_CANCELED, $cardPayment->fresh()->status);
        $this->assertSame(PaymentIntent::STATUS_CANCELED, $this->stripe->status('pi_test_1'));
    }
}
