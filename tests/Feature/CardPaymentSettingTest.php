<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Livewire\Pos\Checkout;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CardPaymentSettingTest extends TestCase
{
    use RefreshDatabase;

    private function tableWithOpenOrder(): Table
    {
        $table = Table::create(['number' => '1', 'name' => 'Tisch 1']);
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
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 5]);

        return $table;
    }

    public function test_card_payment_is_enabled_by_default(): void
    {
        $this->assertTrue(Setting::cardPaymentEnabled());

        Livewire::test(Checkout::class, ['table' => $this->tableWithOpenOrder()])
            ->assertSee('Rest · Karte')
            ->call('startRemainingCardPayment')
            ->assertDispatched('start-native-card-payment');
    }

    public function test_disabled_card_payment_hides_buttons_and_blocks_start(): void
    {
        Setting::putValue(Setting::CARD_PAYMENT_ENABLED, false);

        Livewire::test(Checkout::class, ['table' => $this->tableWithOpenOrder()])
            ->assertSee('Rest · Bar')
            ->assertDontSee('Rest · Karte')
            ->assertDontSee('Auswahl · Karte')
            ->call('startRemainingCardPayment')
            ->assertNotDispatched('start-native-card-payment')
            ->assertHasErrors('payment');
    }

    public function test_admin_can_toggle_card_payment(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SettingsIndex::class)
            ->assertSet('cardPaymentEnabled', true)
            ->set('cardPaymentEnabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse(Setting::cardPaymentEnabled());
    }
}
