<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Livewire\Pos\Index as PosIndex;
use App\Livewire\Pos\StationaryIndex;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\ProductionStation;
use App\Models\ProductReservation;
use App\Models\SelfOrder;
use App\Models\Table;
use App\Models\TableOrderSession;
use App\Services\OrderCancellationService;
use App\Services\OrderService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private ProductCategory $category;

    private Table $table;

    private StockService $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->table = Table::create(['number' => '1', 'name' => 'Tisch 1']);

        $group = ProductGroup::create(['name' => 'Getränke']);

        $this->category = ProductCategory::create([
            'product_group_id' => $group->id,
            'printer_id' => Printer::create(['name' => 'Bar'])->id,
            'production_station_id' => ProductionStation::create(['name' => 'Bar'])->id,
            'name' => 'Bier',
        ]);

        $this->stock = app(StockService::class);
    }

    private function product(int $quantity, string $name = 'Bier'): Product
    {
        return Product::create([
            'name' => $name,
            'price' => 5,
            'available_quantity' => $quantity,
            'is_active' => true,
            'product_category_id' => $this->category->id,
        ]);
    }

    public function test_reserve_is_limited_by_stock_across_holders(): void
    {
        $product = $this->product(5);

        $this->assertSame(3, $this->stock->reserve('pos:a', $product->id, 3));
        $this->assertSame(2, $this->stock->reserve('guest:b', $product->id, 4));
        $this->assertSame([$product->id => 0], $this->stock->remainingMap([$product]));

        $this->stock->reserve('pos:a', $product->id, 1);

        $this->assertSame([$product->id => 2], $this->stock->remainingMap([$product->fresh()]));
    }

    public function test_unlimited_products_are_never_reserved(): void
    {
        $product = $this->product(-1);

        $this->assertSame(50, $this->stock->reserve('pos:a', $product->id, 50));
        $this->assertSame(0, ProductReservation::count());
        $this->assertSame([$product->id => null], $this->stock->remainingMap([$product]));
    }

    public function test_expired_reservations_do_not_count(): void
    {
        $product = $this->product(5);

        $this->stock->reserve('pos:a', $product->id, 5);

        $this->travel(StockService::TTL_MINUTES + 1)->minutes();

        $this->assertSame([$product->id => 5], $this->stock->remainingMap([$product]));
    }

    public function test_create_order_consumes_stock_and_releases_reservation(): void
    {
        $product = $this->product(10);

        $this->stock->reserve('pos:a', $product->id, 3);

        app(OrderService::class)->createOrder(
            $this->table->id,
            [$product->id => ['id' => $product->id, 'price' => 5, 'quantity' => 3, 'note' => '']],
            reservationHolder: 'pos:a',
        );

        $this->assertSame(7, $product->fresh()->available_quantity);
        $this->assertSame(0, ProductReservation::count());
    }

    public function test_create_order_fails_when_others_hold_the_stock(): void
    {
        $product = $this->product(2);

        $this->stock->reserve('pos:other', $product->id, 2);

        $this->expectException(InsufficientStockException::class);

        try {
            app(OrderService::class)->createOrder(
                $this->table->id,
                [$product->id => ['id' => $product->id, 'price' => 5, 'quantity' => 1, 'note' => '']],
                reservationHolder: 'pos:a',
            );
        } finally {
            $this->assertSame(2, $product->fresh()->available_quantity);
            $this->assertSame(0, Order::count());
        }
    }

    public function test_cancellation_restocks(): void
    {
        $product = $this->product(4);

        $order = Order::create(['table_id' => $this->table->id, 'status' => Order::STATUS_OPEN]);
        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'price' => 5,
        ]);

        app(OrderCancellationService::class)->cancel($item, 2, 'Falsch boniert');

        $this->assertSame(6, $product->fresh()->available_quantity);
    }

    public function test_purge_keeps_reservations_of_paid_self_orders(): void
    {
        $product = $this->product(10);

        $session = TableOrderSession::create([
            'table_id' => $this->table->id,
            'token_hash' => hash('sha256', 'token'),
            'active' => true,
        ]);

        $paid = SelfOrder::create([
            'table_order_session_id' => $session->id,
            'table_id' => $this->table->id,
            'status' => SelfOrder::STATUS_PAID,
            'amount' => 10,
        ]);

        $this->stock->reserve('self:guest', $product->id, 2);
        $this->stock->transferToSelfOrder('self:guest', $paid);
        $this->stock->reserve('pos:a', $product->id, 3);

        $this->travel(StockService::SELF_ORDER_TTL_MINUTES + 1)->minutes();

        $this->assertSame(1, $this->stock->purgeExpired());
        $this->assertSame(
            [StockService::selfOrderHolder($paid)],
            ProductReservation::pluck('holder')->all()
        );
    }

    public function test_pos_tile_shows_stock_badge_only_below_twenty(): void
    {
        $low = $this->product(5, 'Knapp');
        $plenty = $this->product(25, 'Genug');
        $unlimited = $this->product(-1, 'Unbegrenzt');

        Livewire::test(PosIndex::class)
            ->call('selectTable', $this->table->id)
            ->assertSee('Knapp')
            ->assertSeeHtml('title="Noch verfügbar"')
            ->assertViewHas('stock', fn (array $stock) => $stock[$low->id] === 5
                && $stock[$plenty->id] === 25
                && $stock[$unlimited->id] === null);

        Product::query()->where('name', 'Knapp')->update(['available_quantity' => 30]);

        Livewire::test(PosIndex::class)
            ->call('selectTable', $this->table->id)
            ->assertDontSeeHtml('title="Noch verfügbar"');
    }

    public function test_stationary_tile_shows_stock_badge_only_below_twenty(): void
    {
        $stationary = Table::create(['number' => 'ST-1', 'name' => 'Kasse 1', 'is_stationary' => true]);

        $this->product(5, 'Knapp');

        Livewire::test(StationaryIndex::class, ['table' => $stationary])
            ->assertSee('Knapp')
            ->assertSeeHtml('title="Noch verfügbar"');

        Product::query()->where('name', 'Knapp')->update(['available_quantity' => -1]);

        Livewire::test(StationaryIndex::class, ['table' => $stationary])
            ->assertDontSeeHtml('title="Noch verfügbar"');
    }

    public function test_pos_cart_reserves_and_caps_quantity(): void
    {
        $product = $this->product(2);

        $component = Livewire::test(PosIndex::class)
            ->call('selectTable', $this->table->id)
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id);

        $this->assertSame(2, $component->get('cart')[$product->id]['quantity']);
        $this->assertSame(2, (int) ProductReservation::sum('quantity'));

        $component->call('backToTables');

        $this->assertSame(0, ProductReservation::count());
    }
}
