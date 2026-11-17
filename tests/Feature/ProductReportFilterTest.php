<?php

namespace Tests\Feature;

use App\Livewire\Admin\ProductReports\Index as ProductReports;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProductReportFilterTest extends TestCase
{
    use RefreshDatabase;

    private ProductGroup $drinks;

    private ProductCategory $beer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->drinks = ProductGroup::create(['name' => 'Getränke']);
        $food = ProductGroup::create(['name' => 'Speisen']);

        $this->beer = ProductCategory::create(['product_group_id' => $this->drinks->id, 'name' => 'Bier']);
        $warm = ProductCategory::create(['product_group_id' => $food->id, 'name' => 'Warm']);

        $order = Order::create([
            'table_id' => Table::create(['number' => '1'])->id,
            'status' => Order::STATUS_PAID,
        ]);

        foreach ([['Märzen', 4.5, $this->beer, 3], ['Schnitzel', 12, $warm, 2]] as [$name, $price, $category, $quantity]) {
            $product = Product::create([
                'name' => $name,
                'price' => $price,
                'available_quantity' => -1,
                'is_active' => true,
                'product_category_id' => $category->id,
            ]);

            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $price,
            ]);
        }
    }

    public function test_filter_by_product_group(): void
    {
        Livewire::test(ProductReports::class)
            ->set('groupId', $this->drinks->id)
            ->assertOk()
            ->assertSee('Märzen')
            ->assertDontSee('Schnitzel');
    }

    public function test_changing_group_drops_category_of_other_group(): void
    {
        $food = ProductGroup::query()->where('name', 'Speisen')->sole();

        Livewire::test(ProductReports::class)
            ->set('categoryId', $this->beer->id)
            ->set('groupId', $food->id)
            ->assertSet('categoryId', null)
            ->assertSee('Schnitzel');
    }

    public function test_group_and_category_names_are_shown(): void
    {
        Livewire::test(ProductReports::class)
            ->assertSee('Getränke')
            ->assertDontSee('product_group_id');
    }

    public function test_pdf_export(): void
    {
        $date = today()->format('Y-m-d');

        Livewire::test(ProductReports::class)
            ->set('groupId', $this->drinks->id)
            ->call('exportPdf')
            ->assertFileDownloaded('produktauswertung-'.$date.'.pdf');
    }

    public function test_filter_by_category(): void
    {
        Livewire::test(ProductReports::class)
            ->set('categoryId', $this->beer->id)
            ->assertOk()
            ->assertSee('Märzen')
            ->assertDontSee('Schnitzel')
            ->set('categoryId', '')
            ->assertOk()
            ->assertSee('Schnitzel');
    }
}
