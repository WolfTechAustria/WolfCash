<?php

namespace Tests\Feature;

use App\Livewire\Admin\DailySummary\Index as DailySummary;
use App\Livewire\Admin\ProductReports\Index as ProductReports;
use App\Livewire\Pos\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Services\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoucherPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::putValue(Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED, false);

        $group = ProductGroup::create(['name' => 'Speisen']);
        $category = ProductCategory::create(['product_group_id' => $group->id, 'name' => 'Warm']);

        $this->product = Product::create([
            'name' => 'Schnitzel',
            'price' => 12,
            'available_quantity' => 10,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);
    }

    private function openOrder(Table $table, int $quantity = 2): Order
    {
        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_OPEN]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'quantity' => $quantity,
            'price' => 12,
        ]);

        return $order;
    }

    public function test_waiter_can_settle_order_with_voucher(): void
    {
        $table = Table::create(['number' => '5', 'name' => 'Tisch 5']);
        $order = $this->openOrder($table);

        Livewire::test(Checkout::class, ['table' => $table])
            ->assertSee('Rest · Bon')
            ->call('payOpen', 'voucher')
            ->assertHasNoErrors()
            ->assertSet('paymentFinished', true);

        $payment = Payment::sole();

        $this->assertSame(Payment::VOUCHER, $payment->payment_method);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        $this->assertSame($payment->id, $order->items()->first()->payment_id);

        // Bereits an der stationären Kassa abgebucht: Tischbuchung wird ausgeglichen.
        $this->assertSame(12, $this->product->fresh()->available_quantity);
    }

    public function test_partial_voucher_payment_links_only_the_split_item(): void
    {
        $table = Table::create(['number' => '5', 'name' => 'Tisch 5']);
        $order = $this->openOrder($table, 3);
        $itemId = $order->items()->first()->id;

        Livewire::test(Checkout::class, ['table' => $table])
            ->call('addToPayment', $itemId)
            ->call('paySelected', 'voucher')
            ->assertHasNoErrors();

        $voucherItem = OrderItem::whereNotNull('payment_id')->sole();

        $this->assertSame(1, $voucherItem->quantity);
        $this->assertNull(OrderItem::find($itemId)->payment_id);
        $this->assertSame(11, $this->product->fresh()->available_quantity);
    }

    public function test_voucher_is_not_offered_at_the_stationary_till(): void
    {
        $table = Table::create(['number' => 'ST-1', 'name' => 'Kasse 1', 'is_stationary' => true]);
        $this->openOrder($table);

        Livewire::test(Checkout::class, ['table' => $table])
            ->assertDontSee('Rest · Bon')
            ->call('payOpen', 'voucher')
            ->assertHasErrors('payment');

        $this->assertSame(0, Payment::count());
    }

    public function test_disabled_voucher_setting_and_unknown_methods_are_rejected(): void
    {
        Setting::putValue(Setting::VOUCHER_PAYMENT_ENABLED, false);

        $table = Table::create(['number' => '5', 'name' => 'Tisch 5']);
        $this->openOrder($table);

        Livewire::test(Checkout::class, ['table' => $table])
            ->assertDontSee('Rest · Bon')
            ->call('payOpen', 'voucher')
            ->assertHasErrors('payment')
            ->call('payOpen', 'house')
            ->assertHasErrors('payment');

        $this->assertSame(0, Payment::count());
    }

    public function test_voucher_payments_are_not_counted_as_revenue(): void
    {
        $this->actingAs(User::factory()->create());

        $cashTable = Table::create(['number' => '1', 'name' => 'Tisch 1']);
        $voucherTable = Table::create(['number' => '2', 'name' => 'Tisch 2']);

        $this->openOrder($cashTable, 1);
        $this->openOrder($voucherTable, 2);

        Livewire::test(Checkout::class, ['table' => $cashTable])->call('payOpen', 'cash');
        Livewire::test(Checkout::class, ['table' => $voucherTable])->call('payOpen', 'voucher');

        $summary = Livewire::test(DailySummary::class)->instance()->summary;

        $this->assertEquals(12, $summary['payable_amount']);
        $this->assertEquals(12, $summary['paid_amount']);
        $this->assertEquals(12, $summary['cash_amount']);
        $this->assertEquals(24, $summary['voucher_amount']);
        $this->assertEquals(0, $summary['open_amount']);

        $rows = Livewire::test(ProductReports::class)->instance()->rows;

        $this->assertSame(1, $rows->firstWhere('product_id', $this->product->id)['quantity']);
    }

    public function test_cancelling_a_voucher_item_does_not_restock_twice(): void
    {
        $table = Table::create(['number' => '5', 'name' => 'Tisch 5']);
        $order = $this->openOrder($table, 2);

        Livewire::test(Checkout::class, ['table' => $table])->call('payOpen', 'voucher');

        $this->assertSame(12, $this->product->fresh()->available_quantity);

        app(OrderCancellationService::class)->cancel($order->items()->first(), 1, 'Bon zurückgegeben');

        $this->assertSame(12, $this->product->fresh()->available_quantity);
    }
}
