<?php

namespace Tests\Feature;

use App\Livewire\Admin\Tables\Index as TablesIndex;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Services\PaymentReceiptService;
use App\Services\PrintService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StationaryPrinterTest extends TestCase
{
    use RefreshDatabase;

    private Printer $defaultPrinter;

    private Printer $tillPrinter;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->defaultPrinter = Printer::create(['name' => 'Standard', 'is_active' => true]);
        $this->tillPrinter = Printer::create(['name' => 'Kassa 2', 'is_active' => true]);

        Setting::putValue(Setting::STATIONARY_PRINTER_ID, $this->defaultPrinter->id);
        Setting::putValue(Setting::RECEIPT_PRINTER_ID, $this->defaultPrinter->id);
    }

    private function orderWithItem(Table $table): array
    {
        $group = ProductGroup::create(['name' => 'Getränke']);
        $category = ProductCategory::create(['product_group_id' => $group->id, 'name' => 'Bier']);
        $product = Product::create([
            'name' => 'Bier',
            'price' => 5,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);

        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_OPEN]);
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 5]);

        return [$order, $item];
    }

    public function test_admin_can_assign_own_printer_to_stationary_till(): void
    {
        $this->actingAs(User::factory()->create());

        $till = Table::create(['number' => 'ST-1', 'name' => 'Kasse 1', 'is_stationary' => true]);

        Livewire::test(TablesIndex::class)
            ->assertSee('Standard (Standard)')
            ->call('setPrinter', $till->id, (string) $this->tillPrinter->id);

        $this->assertSame($this->tillPrinter->id, (int) $till->fresh()->printer_id);

        Livewire::test(TablesIndex::class)->call('setPrinter', $till->id, '');

        $this->assertNull($till->fresh()->printer_id);
    }

    public function test_printer_can_only_be_assigned_to_stationary_tills(): void
    {
        $this->actingAs(User::factory()->create());

        $table = Table::create(['number' => '1', 'name' => 'Tisch 1']);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(TablesIndex::class)->call('setPrinter', $table->id, (string) $this->tillPrinter->id);
    }

    public function test_stationary_bons_use_till_printer_or_default(): void
    {
        $ownTill = Table::create(['number' => 'ST-1', 'is_stationary' => true, 'printer_id' => $this->tillPrinter->id]);
        $defaultTill = Table::create(['number' => 'ST-2', 'is_stationary' => true]);

        [$ownOrder, $ownItem] = $this->orderWithItem($ownTill);
        [$defaultOrder, $defaultItem] = $this->orderWithItem($defaultTill);

        app(PrintService::class)->createStationaryOrderJob($ownOrder, [$ownItem]);
        app(PrintService::class)->createStationaryOrderJob($defaultOrder, [$defaultItem]);

        $this->assertSame($this->tillPrinter->id, PrintJob::where('order_id', $ownOrder->id)->value('printer_id'));
        $this->assertSame($this->defaultPrinter->id, PrintJob::where('order_id', $defaultOrder->id)->value('printer_id'));
    }

    public function test_receipts_of_a_till_with_own_printer_are_printed_there(): void
    {
        $ownTill = Table::create(['number' => 'ST-1', 'is_stationary' => true, 'printer_id' => $this->tillPrinter->id]);
        $waiterTable = Table::create(['number' => '1', 'printer_id' => $this->tillPrinter->id]);

        foreach ([[$ownTill, $this->tillPrinter], [$waiterTable, $this->defaultPrinter]] as [$table, $expectedPrinter]) {
            [$order] = $this->orderWithItem($table);

            $payment = Payment::create(['order_id' => $order->id, 'amount' => 5, 'payment_method' => Payment::CASH]);

            $job = app(PaymentReceiptService::class)->createAndDispatch(
                payment: $payment,
                order: $order,
                items: [['name' => 'Bier', 'quantity' => 1, 'unit_price' => 5, 'total' => 5]],
            );

            $this->assertSame($expectedPrinter->id, $job->printer_id);
        }
    }
}
