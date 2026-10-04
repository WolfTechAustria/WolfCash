<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Http\Middleware\EnsureFloorDevice;
use App\Livewire\Pos\Index as PosIndex;
use App\Models\Device;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\Table;
use App\Services\OrderCancellationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PosTableOpenAmountTest extends TestCase
{
    use RefreshDatabase;

    public function test_occupied_table_shows_only_the_open_amount(): void
    {
        Queue::fake();
        Setting::putValue(Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED, false);

        $group = ProductGroup::create(['name' => 'Getränke']);
        $category = ProductCategory::create(['product_group_id' => $group->id, 'name' => 'Bier']);
        $product = Product::create([
            'name' => 'Bier',
            'price' => 4.5,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);

        $table = Table::create(['number' => '8', 'status' => 'occupied']);
        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_OPEN]);

        $beer = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 4, 'price' => 4.5]);
        $order->recalculateTotal();

        // 4 × 4,50 = 18,00 — davon 1 bezahlt und 1 storniert → 9,00 offen.
        app(PaymentService::class)->paySelection($order, [$beer->id => 1], 'cash');
        app(OrderCancellationService::class)->cancel($beer->fresh(), 1, 'Falsch');

        $device = Device::create([
            'uuid' => (string) Str::uuid(),
            'fingerprint' => (string) Str::uuid(),
            'name' => 'Kellner',
            'platform' => 'android',
            'status' => DeviceStatus::Approved,
        ]);

        Livewire::withCookies([EnsureFloorDevice::COOKIE => $device->fingerprint])
            ->test(PosIndex::class)
            ->call('setTableSelectionMode', 'list')
            ->assertSee('9,00 €')
            ->assertDontSee('13,50 €');
    }
}
