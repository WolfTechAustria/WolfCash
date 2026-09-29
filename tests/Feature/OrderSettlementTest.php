<?php

namespace Tests\Feature;

use App\Livewire\Pos\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\Table;
use App\Services\OrderCancellationService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrderSettlementTest extends TestCase
{
    use RefreshDatabase;

    private Table $table;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::putValue(Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED, false);

        $group = ProductGroup::create(['name' => 'Speisen']);
        $category = ProductCategory::create(['product_group_id' => $group->id, 'name' => 'Warm']);

        $this->table = Table::create(['number' => '7', 'name' => 'Tisch 7', 'status' => 'occupied']);
        $this->order = Order::create(['table_id' => $this->table->id, 'status' => Order::STATUS_OPEN]);

        foreach (['A' => 10, 'B' => 10, 'C' => 5] as $name => $price) {
            $product = Product::create([
                'name' => $name,
                'price' => $price,
                'available_quantity' => -1,
                'is_active' => true,
                'product_category_id' => $category->id,
            ]);

            OrderItem::create([
                'order_id' => $this->order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => $price,
            ]);
        }
    }

    private function item(string $name): OrderItem
    {
        return $this->order->items()->whereHas('product', fn ($q) => $q->where('name', $name))->firstOrFail();
    }

    public function test_refunded_item_does_not_let_the_order_close_with_unpaid_items(): void
    {
        $payments = app(PaymentService::class);

        $payments->paySelection($this->order, [$this->item('A')->id => 1], 'cash');
        app(OrderCancellationService::class)->cancel($this->item('A'), 1, 'Rückerstattung');

        // Bezahlt/erstattet 10 €, bezahlt jetzt C – B (10 €) ist weiter offen.
        $payments->paySelection($this->order->fresh(), [$this->item('C')->id => 1], 'cash');

        $this->assertSame(Order::STATUS_OPEN, $this->order->fresh()->status);
        $this->assertSame('occupied', $this->table->fresh()->status);
    }

    public function test_payment_service_closes_order_and_frees_table(): void
    {
        app(PaymentService::class)->payRemaining($this->order, 'cash');

        $this->assertSame(Order::STATUS_PAID, $this->order->fresh()->status);
        $this->assertSame('free', $this->table->fresh()->status);
    }

    public function test_checkout_shows_success_and_can_still_print_after_closing(): void
    {
        Livewire::test(Checkout::class, ['table' => $this->table])
            ->call('payOpen', 'cash')
            ->assertSet('paymentFinished', true)
            ->assertSee('Zahlung erfolgreich');

        $this->assertSame(Order::STATUS_PAID, $this->order->fresh()->status);
    }

    public function test_booking_after_the_order_was_settled_opens_a_new_order(): void
    {
        app(PaymentService::class)->payRemaining($this->order, 'cash');

        $product = Product::query()->where('name', 'B')->firstOrFail();

        $newOrder = app(OrderService::class)->createOrder($this->table->id, [
            ['id' => $product->id, 'quantity' => 1, 'price' => 10],
        ]);

        $this->assertNotSame($this->order->id, $newOrder->id);
        $this->assertSame(3, $this->order->items()->count());
        $this->assertSame(1, Order::query()->where('table_id', $this->table->id)->where('status', Order::STATUS_OPEN)->count());
    }
}
