<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Models\DailyClosing;
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
use App\Models\SelfOrder;
use App\Models\Setting;
use App\Models\Table;
use App\Models\TableOrderSession;
use App\Services\SystemResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SystemResetServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedEverything(): array
    {
        $table = Table::create(['number' => '1']);
        $group = ProductGroup::create(['name' => 'Getränke']);
        $printer = Printer::create(['name' => 'Bar']);
        $station = ProductionStation::create(['name' => 'Bar']);
        $category = ProductCategory::create([
            'product_group_id' => $group->id,
            'printer_id' => $printer->id,
            'production_station_id' => $station->id,
            'name' => 'Bier',
        ]);
        $product = Product::create([
            'name' => 'Bier',
            'price' => 5,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);

        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_PAID]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 5]);
        $payment = Payment::create(['order_id' => $order->id, 'amount' => 5, 'payment_method' => Payment::CASH]);
        PrintJob::create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'payload' => ['items' => []],
        ]);

        DailyClosing::create(['business_date' => '2026-09-01', 'closed_at' => now(), 'snapshot' => []]);

        $device = Device::create([
            'name' => 'Test Device',
            'fingerprint' => 'fp-'.uniqid(),
            'status' => DeviceStatus::Approved,
        ]);

        Setting::putValue(Setting::SELF_ORDERING_ENABLED, true);

        $tos = TableOrderSession::create([
            'table_id' => $table->id,
            'token_hash' => hash('sha256', 'tok-'.uniqid()),
            'active' => true,
        ]);

        SelfOrder::create([
            'table_order_session_id' => $tos->id,
            'table_id' => $table->id,
            'status' => 'draft',
            'amount' => 0,
        ]);

        return compact('table', 'group', 'printer', 'station', 'category', 'product', 'order', 'payment', 'device');
    }

    public function test_resolve_categories_forces_sales_dependency(): void
    {
        $service = app(SystemResetService::class);

        $this->assertEqualsCanonicalizing(
            ['tables', 'sales'],
            $service->resolveCategories(['tables'])
        );

        $this->assertEqualsCanonicalizing(
            ['products', 'sales'],
            $service->resolveCategories(['products'])
        );

        $this->assertEqualsCanonicalizing(
            ['printing', 'sales'],
            $service->resolveCategories(['printing'])
        );

        $this->assertEqualsCanonicalizing(
            ['devices'],
            $service->resolveCategories(['devices'])
        );
    }

    public function test_counts_reports_expected_numbers(): void
    {
        $this->seedEverything();

        $counts = app(SystemResetService::class)->counts(['sales']);

        $this->assertSame(1, $counts['sales']['Bestellungen']);
        $this->assertSame(1, $counts['sales']['Zahlungen']);
    }

    public function test_reset_sales_only_clears_transactional_data(): void
    {
        $seed = $this->seedEverything();

        app(SystemResetService::class)->reset(['sales']);

        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(0, DB::table('payments')->count());
        $this->assertSame(0, DB::table('print_jobs')->count());
        $this->assertSame(0, DB::table('self_orders')->count());
        $this->assertSame(0, DB::table('table_order_sessions')->count());
        $this->assertSame(0, DB::table('daily_closings')->count());

        // untouched
        $this->assertSame(1, DB::table('tables')->count());
        $this->assertSame(1, DB::table('products')->count());
        $this->assertSame(1, DB::table('printers')->count());
        $this->assertSame(1, DB::table('devices')->count());
    }

    public function test_reset_tables_alone_forces_sales_and_cascades_cleanly(): void
    {
        $this->seedEverything();

        // Selecting ONLY "tables" (not "sales") must still succeed
        // despite self_orders.table_id being restrictOnDelete.
        app(SystemResetService::class)->reset(['tables']);

        $this->assertSame(0, DB::table('tables')->count());
        $this->assertSame(0, DB::table('orders')->count());
        $this->assertSame(0, DB::table('self_orders')->count());
        $this->assertSame(0, DB::table('table_order_sessions')->count());

        // untouched
        $this->assertSame(1, DB::table('products')->count());
        $this->assertSame(1, DB::table('printers')->count());
    }

    public function test_reset_products_alone_forces_sales(): void
    {
        $this->seedEverything();

        app(SystemResetService::class)->reset(['products']);

        $this->assertSame(0, DB::table('products')->count());
        $this->assertSame(0, DB::table('product_categories')->count());
        $this->assertSame(0, DB::table('product_groups')->count());
        $this->assertSame(0, DB::table('orders')->count());

        // untouched
        $this->assertSame(1, DB::table('tables')->count());
        $this->assertSame(1, DB::table('printers')->count());
    }

    public function test_reset_printing_alone_forces_sales_and_avoids_restrict(): void
    {
        $this->seedEverything();

        // print_outputs.printer_id is restrictOnDelete — this only
        // succeeds if sales (and thus print_jobs) is cleared first.
        app(SystemResetService::class)->reset(['printing']);

        $this->assertSame(0, DB::table('printers')->count());
        $this->assertSame(0, DB::table('production_stations')->count());
        $this->assertSame(0, DB::table('orders')->count());

        // untouched
        $this->assertSame(1, DB::table('tables')->count());
        $this->assertSame(1, DB::table('products')->count());
    }

    public function test_reset_devices_is_independent(): void
    {
        $this->seedEverything();

        app(SystemResetService::class)->reset(['devices']);

        $this->assertSame(0, DB::table('devices')->count());

        // untouched
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('tables')->count());
    }

    public function test_reset_settings_clears_table(): void
    {
        $this->seedEverything();

        $this->assertGreaterThan(0, DB::table('settings')->count());

        app(SystemResetService::class)->reset(['settings']);

        $this->assertSame(0, DB::table('settings')->count());
    }

    public function test_reset_devices_blocked_by_payment_reference_rolls_back_everything(): void
    {
        $seed = $this->seedEverything();

        // Force the edge case: a payment referencing the device.
        // Deliberately reset ONLY "devices" (not "sales") so the
        // payment row survives long enough to actually block the
        // device deletion — combining it with a sales-forcing
        // category would cascade the payment away first.
        DB::table('payments')->where('id', $seed['payment']->id)->update([
            'device_id' => $seed['device']->id,
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            app(SystemResetService::class)->reset(['devices']);
        } finally {
            // Transaction must have rolled back — nothing deleted at all.
            $this->assertSame(1, DB::table('devices')->count());
            $this->assertSame(1, DB::table('orders')->count());
            $this->assertSame(1, DB::table('payments')->count());
        }
    }

    public function test_export_data_contains_selected_categories(): void
    {
        $this->seedEverything();

        $export = app(SystemResetService::class)->exportData(['sales']);

        $this->assertArrayHasKey('orders', $export['data']);
        $this->assertCount(1, $export['data']['orders']);
        $this->assertArrayHasKey('payments', $export['data']);
    }
}
