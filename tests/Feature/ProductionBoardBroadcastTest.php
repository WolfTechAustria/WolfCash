<?php

namespace Tests\Feature;

use App\Events\ProductionBoardChanged;
use App\Livewire\Production\Index as ProductionIndex;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\ProductionStation;
use App\Models\Table;
use App\Services\OrderService;
use App\Services\PrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class ProductionBoardBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private Table $table;

    private ProductionStation $kitchen;

    private ProductionStation $bar;

    private Product $food;

    private Product $drink;

    protected function setUp(): void
    {
        parent::setUp();

        $this->table = Table::create(['number' => '3', 'name' => 'Tisch 3']);
        $printer = Printer::create([
            'name' => 'Küche',
            'is_active' => true,
            'print_trigger' => Printer::PRINT_TRIGGER_ON_JOB_COMPLETE,
        ]);
        $group = ProductGroup::create(['name' => 'Alles']);

        $this->kitchen = ProductionStation::create(['name' => 'Küche']);
        $this->bar = ProductionStation::create(['name' => 'Schank']);

        $this->food = $this->product('Gulasch', $group, $printer, $this->kitchen);
        $this->drink = $this->product('Bier', $group, $printer, $this->bar);
    }

    private function product(string $name, ProductGroup $group, Printer $printer, ProductionStation $station): Product
    {
        $category = ProductCategory::create([
            'product_group_id' => $group->id,
            'printer_id' => $printer->id,
            'production_station_id' => $station->id,
            'name' => $name,
        ]);

        return Product::create([
            'name' => $name,
            'price' => 5,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);
    }

    public function test_booking_sends_one_signal_for_all_affected_stations(): void
    {
        Event::fake([ProductionBoardChanged::class]);

        app(OrderService::class)->createOrder(
            $this->table->id,
            [
                ['id' => $this->food->id, 'quantity' => 1, 'price' => 5],
                ['id' => $this->drink->id, 'quantity' => 2, 'price' => 5],
            ],
            app(PrintService::class),
        );

        Event::assertDispatchedTimes(ProductionBoardChanged::class, 1);
        Event::assertDispatched(
            ProductionBoardChanged::class,
            fn (ProductionBoardChanged $event) => collect($event->stationIds)->sort()->values()->all()
                === collect([$this->kitchen->id, $this->bar->id])->sort()->values()->all()
        );
    }

    public function test_completing_an_item_signals_its_station(): void
    {
        app(OrderService::class)->createOrder(
            $this->table->id,
            [['id' => $this->food->id, 'quantity' => 1, 'price' => 5]],
            app(PrintService::class),
        );

        $job = PrintJob::query()->where('type', PrintJob::TYPE_PRODUCTION)->firstOrFail();

        Event::fake([ProductionBoardChanged::class]);

        Livewire::test(ProductionIndex::class)
            ->call('completeGroupedItem', $job->id, $job->payload['items'][0]['order_item_id']);

        Event::assertDispatched(
            ProductionBoardChanged::class,
            fn (ProductionBoardChanged $event) => $event->stationIds === [$this->kitchen->id]
        );
    }

    public function test_signal_for_other_station_does_not_rerender(): void
    {
        $component = Livewire::test(ProductionIndex::class, ['station' => $this->kitchen->id]);

        app(OrderService::class)->createOrder(
            $this->table->id,
            [['id' => $this->food->id, 'quantity' => 1, 'price' => 5]],
            app(PrintService::class),
        );

        $component->call('refreshBoard', ['stationIds' => [$this->bar->id]])
            ->assertDontSee('Gulasch');
    }

    public function test_signal_for_own_station_rerenders(): void
    {
        $component = Livewire::test(ProductionIndex::class, ['station' => $this->kitchen->id])
            ->assertDontSee('Gulasch');

        app(OrderService::class)->createOrder(
            $this->table->id,
            [['id' => $this->food->id, 'quantity' => 1, 'price' => 5]],
            app(PrintService::class),
        );

        $component->call('refreshBoard', ['stationIds' => [$this->kitchen->id]])
            ->assertSee('Gulasch');
    }
}
