<?php

namespace Tests\Feature;

use App\Livewire\Admin\PrintJobs\Index as PrintJobsIndex;
use App\Livewire\Production\Index as ProductionIndex;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintOutput;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\ProductionStation;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Printing\PrintTransport;
use App\Printing\RenderedPrint;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Durchgängig mit synchroner Queue: vom Bonieren/Kassieren bis zum
 * Status in der Druckjob-Übersicht.
 */
class PrintJobStatusTest extends TestCase
{
    use RefreshDatabase;

    private Table $table;

    private Printer $printer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->table = Table::create(['number' => '3', 'name' => 'Tisch 3']);
        $this->printer = Printer::create(['name' => 'Küche', 'is_active' => true]);

        $station = ProductionStation::create(['name' => 'Küche']);
        $group = ProductGroup::create(['name' => 'Speisen']);
        $category = ProductCategory::create([
            'product_group_id' => $group->id,
            'printer_id' => $this->printer->id,
            'production_station_id' => $station->id,
            'name' => 'Warm',
        ]);

        $this->product = Product::create([
            'name' => 'Gulasch',
            'price' => 9,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);
    }

    private function book(): Order
    {
        return app(OrderService::class)->createOrder(
            $this->table->id,
            [['id' => $this->product->id, 'quantity' => 1, 'price' => 9]],
            app(PrintService::class),
        );
    }

    private function productionJob(): PrintJob
    {
        return PrintJob::query()->where('type', PrintJob::TYPE_PRODUCTION)->firstOrFail();
    }

    private function receiptJob(): PrintJob
    {
        return PrintJob::query()->where('type', PrintJob::TYPE_RECEIPT)->firstOrFail();
    }

    private function enableReceipts(): void
    {
        Setting::putValue(Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED, true);
        Setting::putValue(Setting::RECEIPT_PRINTER_ID, $this->printer->id);
    }

    private function failPrinting(): void
    {
        $this->app->instance(PrintTransport::class, new class implements PrintTransport
        {
            public function print(Printer $printer, RenderedPrint $document): void
            {
                throw new RuntimeException('Drucker offline');
            }
        });
    }

    public function test_immediate_production_ticket_is_printed(): void
    {
        $this->book();

        $this->assertSame(PrintJob::STATUS_PRINTED, $this->productionJob()->status);
    }

    public function test_receipt_job_follows_its_printed_output(): void
    {
        $this->enableReceipts();

        app(PaymentService::class)->payRemaining($this->book(), 'cash');

        $this->assertSame(PrintOutput::STATUS_PRINTED, PrintOutput::query()->firstOrFail()->status);
        $this->assertSame(PrintJob::STATUS_PRINTED, $this->receiptJob()->status);
        $this->assertNotNull($this->receiptJob()->printed_at);
    }

    public function test_item_trigger_job_is_printed_once_production_is_complete(): void
    {
        $this->printer->update(['print_trigger' => Printer::PRINT_TRIGGER_ON_ITEM_COMPLETE]);

        $this->book();

        $job = $this->productionJob();
        $this->assertSame(PrintJob::STATUS_PENDING, $job->status);

        Livewire::test(ProductionIndex::class)
            ->call('completeGroupedItem', $job->id, $job->payload['items'][0]['order_item_id']);

        $this->assertSame(PrintOutput::STATUS_PRINTED, PrintOutput::query()->firstOrFail()->status);
        $this->assertSame(PrintJob::STATUS_PRINTED, $job->fresh()->status);
    }

    public function test_failed_receipt_is_shown_and_retry_prints_it(): void
    {
        $this->enableReceipts();
        $order = $this->book();
        $this->failPrinting();

        try {
            app(PaymentService::class)->payRemaining($order, 'cash');
        } catch (RuntimeException) {
            // Synchrone Queue wirft den Druckfehler bis hierher durch.
        }

        $job = $this->receiptJob();
        $this->assertSame(PrintJob::STATUS_FAILED, $job->status);
        $this->assertStringContainsString('Drucker offline', $job->error_message);

        $this->app->forgetInstance(PrintTransport::class);
        $this->app->singleton(PrintTransport::class, fn () => new \App\Printing\SimulationPrintTransport);

        $this->actingAs(User::factory()->create());

        Livewire::test(PrintJobsIndex::class)->call('retry', $job->id);

        $this->assertSame(PrintJob::STATUS_PRINTED, $job->fresh()->status);
    }
}
