<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Http\Middleware\EnsureFloorDevice;
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Livewire\Admin\Orders\Show as OrdersShow;
use App\Livewire\Pos\Checkout;
use App\Livewire\Pos\Index as PosIndex;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\ProductionStation;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Printing\ProductionTicketRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OrderItemOriginTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_records_device_and_shows_it_on_orders_and_production_ticket(): void
    {
        Queue::fake();

        $table = Table::create(['number' => '4', 'name' => 'Tisch 4']);
        $printer = Printer::create(['name' => 'Küche']);
        $station = ProductionStation::create(['name' => 'Küche']);
        $group = ProductGroup::create(['name' => 'Speisen']);
        $category = ProductCategory::create([
            'product_group_id' => $group->id,
            'printer_id' => $printer->id,
            'production_station_id' => $station->id,
            'name' => 'Warm',
        ]);
        $product = Product::create([
            'name' => 'Gulasch',
            'price' => 9,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);

        $device = Device::create([
            'uuid' => (string) Str::uuid(),
            'fingerprint' => (string) Str::uuid(),
            'name' => 'Kellner Anna',
            'platform' => 'android',
            'status' => DeviceStatus::Approved,
        ]);

        Livewire::withCookies([EnsureFloorDevice::COOKIE => $device->fingerprint])
            ->test(PosIndex::class)
            ->call('selectTable', $table->id)
            ->call('addProduct', $product->id)
            ->call('bonieren');

        $item = OrderItem::query()->firstOrFail();

        $this->assertSame($device->id, $item->device_id);
        $this->assertSame('Kellner Anna', $item->origin_label);

        $job = PrintJob::query()->where('type', PrintJob::TYPE_PRODUCTION)->firstOrFail();

        $this->assertSame('Kellner Anna', $job->payload['origin']);

        $lines = app(ProductionTicketRenderer::class)->render($job)[0]->lines;

        $this->assertContains('Von: Kellner Anna', $lines);

        $this->actingAs(User::factory()->create());

        Livewire::test(OrdersIndex::class, ['period' => 'all'])
            ->assertSee('Kellner Anna');

        Livewire::test(OrdersShow::class, ['order' => Order::query()->firstOrFail()])
            ->assertSee('Gerät: Kellner Anna');
    }

    public function test_checkout_records_paying_device(): void
    {
        Setting::putValue(Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED, false);

        $table = Table::create(['number' => '9', 'name' => 'Tisch 9', 'status' => 'occupied']);
        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_OPEN]);
        $group = ProductGroup::create(['name' => 'Getränke']);
        $category = ProductCategory::create(['product_group_id' => $group->id, 'name' => 'Bier']);
        $product = Product::create([
            'name' => 'Bier',
            'price' => 5,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 5,
        ]);

        $device = Device::create([
            'uuid' => (string) Str::uuid(),
            'fingerprint' => (string) Str::uuid(),
            'name' => 'Kassa Theke',
            'platform' => 'web',
            'status' => DeviceStatus::Approved,
        ]);

        Livewire::withCookies([EnsureFloorDevice::COOKIE => $device->fingerprint])
            ->test(Checkout::class, ['table' => $table])
            ->call('payOpen', 'cash')
            ->assertSet('paymentFinished', true);

        $this->assertSame($device->id, Payment::query()->firstOrFail()->device_id);

        $this->actingAs(User::factory()->create());

        Livewire::test(OrdersShow::class, ['order' => $order->fresh()])
            ->assertSee('Kassa Theke');
    }
}
