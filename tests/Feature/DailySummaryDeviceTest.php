<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Livewire\Admin\DailySummary\Index as DailySummary;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Table;
use App\Models\User;
use App\Services\DailySummaryReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class DailySummaryDeviceTest extends TestCase
{
    use RefreshDatabase;

    private Device $anna;

    private Device $ben;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $group = ProductGroup::create(['name' => 'Getränke']);
        $category = ProductCategory::create(['product_group_id' => $group->id, 'name' => 'Bier']);
        $product = Product::create([
            'name' => 'Bier',
            'price' => 5,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);

        $this->anna = $this->device('Kellner Anna');
        $this->ben = $this->device('Kellner Ben');

        // Anna: 4 Bier boniert, bar kassiert (20 €).
        $this->paidOrder('1', $product, $this->anna, 4, Payment::CASH);

        // Ben: 2 Bier boniert, mit Karte kassiert (10 €).
        $this->paidOrder('2', $product, $this->ben, 2, Payment::CARD);

        // Ben: 3 Bier boniert, noch offen (15 €).
        $table = Table::create(['number' => '3']);
        $open = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_OPEN]);
        OrderItem::create([
            'order_id' => $open->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 5,
            'device_id' => $this->ben->id,
        ]);

        // Self-Order ohne Gerät: 1 Bier, online bezahlt (5 €).
        $this->paidOrder('4', $product, null, 1, Payment::CARD);
    }

    private function device(string $name): Device
    {
        return Device::create([
            'uuid' => (string) Str::uuid(),
            'fingerprint' => (string) Str::uuid(),
            'name' => $name,
            'platform' => 'android',
            'status' => DeviceStatus::Approved,
        ]);
    }

    private function paidOrder(string $tableNumber, Product $product, ?Device $device, int $quantity, string $method): void
    {
        $table = Table::create(['number' => $tableNumber]);
        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_PAID]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => $quantity * 5,
            'payment_method' => $method,
            'device_id' => $device?->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => 5,
            'device_id' => $device?->id,
            'payment_id' => $payment->id,
            'paid_at' => now(),
        ]);
    }

    public function test_unfiltered_summary_and_device_breakdown(): void
    {
        $component = Livewire::test(DailySummary::class)
            ->assertSee('Nach Gerät')
            ->assertSee('Kellner Anna')
            ->assertSee('Ohne Gerät');

        $summary = $component->instance()->summary;
        $this->assertEquals(50.0, $summary['payable_amount']);
        $this->assertEquals(35.0, $summary['paid_amount']);
        $this->assertEquals(15.0, $summary['open_amount']);

        $devices = app(DailySummaryReport::class)->build(today())['devices']->keyBy('name');

        $this->assertEquals(20.0, $devices['Kellner Anna']['paid_amount']);
        $this->assertEquals(20.0, $devices['Kellner Anna']['cash_amount']);
        $this->assertEquals(25.0, $devices['Kellner Ben']['booked_amount']);
        $this->assertEquals(10.0, $devices['Kellner Ben']['card_amount']);
        $this->assertEquals(5.0, $devices['Ohne Gerät']['paid_amount']);
        $this->assertSame('Ohne Gerät', $devices->keys()->last());
    }

    public function test_filter_by_device_restricts_all_figures(): void
    {
        $summary = Livewire::test(DailySummary::class)
            ->call('filterDevice', (string) $this->ben->id)
            ->assertSee('Gefiltert auf')
            ->assertDontSee('Tagesabschluss durchführen')
            ->instance()
            ->summary;

        $this->assertEquals(25.0, $summary['payable_amount']);
        $this->assertEquals(10.0, $summary['paid_amount']);
        $this->assertEquals(10.0, $summary['card_amount']);
        $this->assertEquals(0.0, $summary['cash_amount']);
        $this->assertEquals(15.0, $summary['open_amount']);
        $this->assertSame(2, $summary['orders_total']);
        $this->assertSame(1, $summary['orders_open']);
        $this->assertSame(1, $summary['payments_count']);
    }

    public function test_filter_without_device(): void
    {
        $summary = Livewire::test(DailySummary::class)
            ->set('device', DailySummaryReport::NO_DEVICE)
            ->instance()
            ->summary;

        $this->assertEquals(5.0, $summary['paid_amount']);
        $this->assertSame(1, $summary['orders_total']);
    }

    public function test_pdf_export_downloads_filtered_overview(): void
    {
        Livewire::test(DailySummary::class)
            ->call('filterDevice', (string) $this->anna->id)
            ->call('exportPdf')
            ->assertFileDownloaded('tagesuebersicht-'.today()->format('Y-m-d').'-kellner-anna.pdf');

        Livewire::test(DailySummary::class)
            ->call('exportPdf')
            ->assertFileDownloaded('tagesuebersicht-'.today()->format('Y-m-d').'.pdf');
    }
}
