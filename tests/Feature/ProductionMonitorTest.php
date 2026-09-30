<?php

namespace Tests\Feature;

use App\Livewire\Production\Index as ProductionIndex;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintOutput;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\ProductionStation;
use App\Models\Table;
use App\Services\OrderCancellationService;
use App\Services\OrderService;
use App\Services\PrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionMonitorTest extends TestCase
{
    use RefreshDatabase;

    private Table $table;

    private ProductionStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        config(['printing.driver' => 'simulation']);

        $this->table = Table::create(['number' => '7', 'name' => 'Tisch 7']);
        $this->station = ProductionStation::create(['name' => 'Küche']);
    }

    private function product(string $name, ?string $trigger, string $printMode = Product::PRINT_GROUPED): Product
    {
        $printer = $trigger === null
            ? null
            : Printer::create(['name' => $name.' Drucker', 'is_active' => true, 'print_trigger' => $trigger]);

        $category = ProductCategory::create([
            'product_group_id' => ProductGroup::firstOrCreate(['name' => 'Speisen'])->id,
            'printer_id' => $printer?->id,
            'production_station_id' => $this->station->id,
            'name' => $name,
        ]);

        return Product::create([
            'name' => $name,
            'price' => 10,
            'available_quantity' => -1,
            'is_active' => true,
            'print_mode' => $printMode,
            'product_category_id' => $category->id,
        ]);
    }

    private function book(Product $product, int $quantity = 1): Order
    {
        return app(OrderService::class)->createOrder(
            $this->table->id,
            [['id' => $product->id, 'quantity' => $quantity, 'price' => 10]],
            app(PrintService::class),
        );
    }

    private function job(): PrintJob
    {
        return PrintJob::query()->where('type', PrintJob::TYPE_PRODUCTION)->latest('id')->firstOrFail();
    }

    private function itemId(PrintJob $job): int
    {
        return (int) $job->payload['items'][0]['order_item_id'];
    }

    public function test_immediately_printed_bons_are_not_shown(): void
    {
        $this->book($this->product('Pizza', Printer::PRINT_TRIGGER_IMMEDIATE));

        $this->assertFalse($this->job()->show_on_monitor);

        Livewire::test(ProductionIndex::class)
            ->assertDontSee('Pizza')
            ->assertSee('Keine offenen Bons');
    }

    public function test_delayed_bons_are_shown(): void
    {
        $this->book($this->product('Gulasch', Printer::PRINT_TRIGGER_ON_JOB_COMPLETE));

        Livewire::test(ProductionIndex::class)->assertSee('Gulasch');
    }

    public function test_bons_without_printer_are_shown_and_not_sent_to_print(): void
    {
        $this->book($this->product('Salat', null));

        $job = $this->job();

        $this->assertTrue($job->show_on_monitor);
        $this->assertFalse($job->ready_to_print);
        $this->assertNotSame(PrintJob::STATUS_FAILED, $job->status);

        Livewire::test(ProductionIndex::class)->assertSee('Salat');
    }

    public function test_receipts_are_not_shown(): void
    {
        PrintJob::create([
            'order_id' => Order::create(['table_id' => $this->table->id])->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'status' => PrintJob::STATUS_PENDING,
            'ready_to_print' => true,
            'payload' => ['items' => []],
        ]);

        Livewire::test(ProductionIndex::class)->assertSee('Keine offenen Bons');
    }

    public function test_tapping_a_bon_completed_on_another_monitor_is_harmless(): void
    {
        $this->book($this->product('Gulasch', Printer::PRINT_TRIGGER_ON_JOB_COMPLETE));
        $job = $this->job();

        $otherMonitor = Livewire::test(ProductionIndex::class);

        Livewire::test(ProductionIndex::class)->call('completeJob', $job->id);

        $otherMonitor
            ->call('completeGroupedItem', $job->id, $this->itemId($job))
            ->assertOk()
            ->call('completeJob', $job->id)
            ->assertOk()
            ->assertDontSee('Gulasch');

        $this->assertNotNull($job->fresh()->production_completed_at);
    }

    public function test_foreign_item_is_ignored(): void
    {
        $this->book($this->product('Gulasch', Printer::PRINT_TRIGGER_ON_JOB_COMPLETE));
        $job = $this->job();

        Livewire::test(ProductionIndex::class)
            ->call('completeGroupedItem', $job->id, 999999)
            ->assertOk();

        $this->assertNull($job->fresh()->production_completed_at);
    }

    public function test_cancelled_quantity_is_shown_and_not_required(): void
    {
        $this->book($this->product('Knödel', Printer::PRINT_TRIGGER_ON_ITEM_COMPLETE, Product::PRINT_SPLIT), 3);
        $job = $this->job();
        $itemId = $this->itemId($job);

        app(OrderCancellationService::class)->cancel(OrderItem::findOrFail($itemId), 1, 'Gast will nicht');

        $monitor = Livewire::test(ProductionIndex::class)
            ->assertSee('STORNO 1x')
            ->assertSee('(2/2)')
            ->assertDontSee('(3/3)');

        $monitor->call('completeItemUnit', $job->id, $itemId);
        $this->assertNull($job->fresh()->production_completed_at);

        $monitor->call('completeItemUnit', $job->id, $itemId);

        $this->assertNotNull($job->fresh()->production_completed_at);
        $this->assertSame(
            2,
            PrintOutput::query()
                ->where('print_job_id', $job->id)
                ->where('type', PrintOutput::TYPE_PRODUCTION)
                ->count()
        );
    }

    public function test_fully_cancelled_bon_can_be_closed_without_confirmation(): void
    {
        $this->book($this->product('Gulasch', Printer::PRINT_TRIGGER_ON_JOB_COMPLETE));
        $job = $this->job();

        app(OrderCancellationService::class)->cancel(OrderItem::findOrFail($this->itemId($job)), 1, 'Falsch boniert');

        Livewire::test(ProductionIndex::class)
            ->assertSee('Bon abschließen')
            ->call('completeJob', $job->id)
            ->assertSee('Keine offenen Bons');
    }
}
