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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

/**
 * Diagnostic sweep: hits every application route with minimal
 * fixtures and reports status codes / exceptions. Not part of the
 * regular regression suite — this is a one-off audit tool.
 */
class AppWideSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sweep_all_routes(): void
    {
        $user = User::factory()->create();

        $table = Table::create(['number' => '1', 'name' => 'Tisch 1']);
        $stationaryTable = Table::create(['number' => 'ST-1', 'name' => 'Kasse 1', 'is_stationary' => true]);

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
        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 5,
        ]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 5,
            'payment_method' => Payment::CASH,
        ]);

        PrintJob::create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'payload' => ['items' => []],
        ]);

        $dailyClosing = DailyClosing::create([
            'business_date' => '2026-09-01',
            'closed_at' => now(),
            'snapshot' => [],
        ]);

        $device = Device::create([
            'name' => 'Test Device',
            'fingerprint' => 'test-fingerprint',
            'status' => DeviceStatus::Approved,
        ]);

        Setting::putValue(Setting::SELF_ORDERING_ENABLED, true);

        $tos = TableOrderSession::create([
            'table_id' => $table->id,
            'token_hash' => hash('sha256', 'plaintoken'),
            'active' => true,
        ]);

        $selfOrder = SelfOrder::create([
            'table_order_session_id' => $tos->id,
            'table_id' => $table->id,
            'status' => 'draft',
            'amount' => 0,
        ]);

        /** @var array<int, array{method: string, uri: string, auth: bool}> $routes */
        $routes = [
            ['GET', '/', false],
            ['GET', '/login', false],
            ['GET', '/pos', false],
            ['GET', "/pos/checkout/{$table->id}", false],
            ['GET', "/pos/stationary/{$stationaryTable->id}", false],
            ['GET', '/production', false],
            ['GET', "/o/plaintoken", false],
            ['GET', "/self-order/{$selfOrder->id}/payment/success", false],
            ['GET', "/self-order/{$selfOrder->id}/payment/cancel", false],
            ['GET', '/dashboard', true],
            ['GET', '/admin', true],
            ['GET', '/admin/devices', true],
            ['GET', '/admin/tables', true],
            ['GET', '/admin/products', true],
            ['GET', '/admin/product-groups', true],
            ['GET', '/admin/product-categories', true],
            ['GET', '/admin/orders', true],
            ["GET", "/admin/orders/{$order->id}", true],
            ['GET', '/admin/cancellations', true],
            ['GET', '/admin/daily-summary', true],
            ['GET', '/admin/daily-closings', true],
            ["GET", "/admin/daily-closings/{$dailyClosing->id}", true],
            ['GET', '/admin/product-reports', true],
            ['GET', '/admin/printers', true],
            ['GET', '/admin/production-stations', true],
            ['GET', '/admin/print-jobs', true],
            ['GET', '/admin/settings', true],
            ['GET', '/admin/system-reset', true],
            ["GET", "/admin/tables/{$table->id}/qr/pdf", true],
            ["GET", "/admin/tables/{$table->id}/qr/png", true],
            ['GET', '/admin/tables/qr/pdf', true],
        ];

        $results = [];

        foreach ($routes as [$method, $uri, $auth]) {
            $client = $auth ? $this->actingAs($user) : $this;

            try {
                $response = $client->call($method, $uri);
                $status = $response->getStatusCode();

                $exceptionInfo = '';

                if ($status >= 500) {
                    $exception = $response->exception ?? null;

                    if ($exception instanceof Throwable) {
                        $exceptionInfo = ' | '.get_class($exception).': '.$exception->getMessage()
                            .' @ '.$exception->getFile().':'.$exception->getLine();
                    }
                }

                $results[] = "{$status} {$method} {$uri}{$exceptionInfo}";
            } catch (Throwable $e) {
                $results[] = 'EXC '.$method.' '.$uri.' | '.get_class($e).': '.$e->getMessage()
                    .' @ '.$e->getFile().':'.$e->getLine();
            }
        }

        fwrite(STDERR, "\n\n===SMOKE_TEST_RESULTS===\n".implode("\n", $results)."\n===END===\n\n");

        $this->assertTrue(true);
    }
}
