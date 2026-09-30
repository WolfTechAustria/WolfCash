<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Http\Middleware\EnsureFloorDevice;
use App\Livewire\Admin\ActivityLog\Index as ActivityLogIndex;
use App\Livewire\Admin\Devices\Index as DevicesIndex;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Livewire\Admin\SystemReset\Index as SystemResetIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Pos\Checkout;
use App\Livewire\Pos\Index as PosIndex;
use App\Models\ActivityLog;
use App\Models\Device;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\OrderCancellationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function product(): Product
    {
        $group = ProductGroup::create(['name' => 'Getränke']);
        $category = ProductCategory::create(['product_group_id' => $group->id, 'name' => 'Bier']);

        return Product::create([
            'name' => 'Bier',
            'price' => 5,
            'available_quantity' => -1,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);
    }

    private function device(string $name): Device
    {
        return Device::create([
            'uuid' => (string) Str::uuid(),
            'fingerprint' => (string) Str::uuid(),
            'name' => $name,
            'platform' => 'android',
            'status' => DeviceStatus::Approved,
        ]);
    }

    public function test_booking_cancellation_and_payment_are_logged_with_device(): void
    {
        Queue::fake();
        Setting::putValue(Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED, false);

        $table = Table::create(['number' => '7', 'name' => 'Tisch 7']);
        $product = $this->product();
        $device = $this->device('Kellner Anna');

        Livewire::withCookies([EnsureFloorDevice::COOKIE => $device->fingerprint])
            ->test(PosIndex::class)
            ->call('selectTable', $table->id)
            ->call('addProduct', $product->id)
            ->call('addProduct', $product->id)
            ->call('bonieren');

        $booking = ActivityLog::query()->where('event', ActivityLog::ORDER_BOOKED)->sole();

        $this->assertSame('Kellner Anna', $booking->device_name);
        $this->assertSame($device->id, $booking->device_id);
        $this->assertStringContainsString('Tisch 7', $booking->description);
        $this->assertStringContainsString('10,00 €', $booking->description);

        $admin = User::factory()->create(['name' => 'Chefin']);
        $item = OrderItem::query()->firstOrFail();

        app(OrderCancellationService::class)->cancel($item, 1, 'Falsch boniert', $admin->id);

        $cancellation = ActivityLog::query()->where('event', ActivityLog::ITEM_CANCELLED)->sole();

        $this->assertSame('Chefin', $cancellation->user_name);
        $this->assertSame('Falsch boniert', $cancellation->properties['reason']);

        Livewire::withCookies([EnsureFloorDevice::COOKIE => $device->fingerprint])
            ->test(Checkout::class, ['table' => $table->fresh()])
            ->call('payOpen', 'cash')
            ->assertSet('paymentFinished', true);

        $payment = ActivityLog::query()->where('event', ActivityLog::PAYMENT_CREATED)->sole();

        $this->assertSame(5.0, (float) $payment->properties['amount']);
        $this->assertSame('Kellner Anna', $payment->device_name);
    }

    public function test_rolled_back_actions_are_not_logged(): void
    {
        try {
            DB::transaction(function (): void {
                app(ActivityLogger::class)->log(ActivityLog::SYSTEM_RESET, 'wird zurückgerollt');

                throw new RuntimeException('Abbruch');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, ActivityLog::query()->count());
    }

    public function test_entries_cannot_be_changed(): void
    {
        app(ActivityLogger::class)->log(ActivityLog::SYSTEM_RESET, 'Test');

        $entry = ActivityLog::query()->sole();

        $this->expectException(LogicException::class);

        $entry->update(['description' => 'manipuliert']);
    }

    public function test_admin_changes_are_logged_with_old_and_new_values(): void
    {
        $admin = User::factory()->create(['name' => 'Chefin']);
        $this->actingAs($admin);

        $product = $this->product();

        Livewire::test(ProductsIndex::class)
            ->call('edit', $product->id)
            ->set('price', '6.5')
            ->call('save');

        $change = ActivityLog::query()->where('event', ActivityLog::PRODUCT_UPDATED)->sole();

        $this->assertSame('Chefin', $change->user_name);
        $this->assertEquals(5, $change->properties['changes']['price']['old']);
        $this->assertEquals(6.5, $change->properties['changes']['price']['new']);

        $device = $this->device('Theke');
        $device->update(['status' => DeviceStatus::Pending]);

        Livewire::test(DevicesIndex::class)->call('block', $device->id);

        $this->assertTrue(
            ActivityLog::query()
                ->where('event', ActivityLog::DEVICE_BLOCKED)
                ->where('subject_id', $device->id)
                ->exists()
        );
    }

    public function test_failed_and_successful_logins_are_logged(): void
    {
        User::factory()->create([
            'name' => 'Chefin',
            'email' => 'chefin@example.test',
            'password' => 'geheim123',
        ]);

        Livewire::test(Login::class)
            ->set('email', 'chefin@example.test')
            ->set('password', 'falsch')
            ->call('login');

        Livewire::test(Login::class)
            ->set('email', 'chefin@example.test')
            ->set('password', 'geheim123')
            ->call('login');

        $this->assertSame(1, ActivityLog::query()->where('event', ActivityLog::LOGIN_FAILED)->count());
        $this->assertSame(
            'Chefin',
            ActivityLog::query()->where('event', ActivityLog::LOGIN)->sole()->user_name
        );
        $this->assertStringNotContainsString(
            'falsch',
            json_encode(ActivityLog::query()->get()->toArray())
        );
    }

    public function test_system_reset_clears_log_and_records_itself_as_first_entry(): void
    {
        $admin = User::factory()->create(['name' => 'Chefin']);
        $this->actingAs($admin);

        $product = $this->product();

        app(ActivityLogger::class)->log(ActivityLog::LOGIN, 'Anmeldung Kellner Max');
        app(ActivityLogger::class)->log(ActivityLog::PAYMENT_CREATED, 'Tisch 3: 5,00 € Bar');

        Livewire::test(SystemResetIndex::class)
            ->set('resetActivityLog', true)
            ->assertSee('Protokolleinträge: ')
            ->set('confirmationText', 'LÖSCHEN')
            ->call('confirmReset')
            ->assertHasNoErrors();

        $entry = ActivityLog::query()->sole();

        $this->assertSame(ActivityLog::SYSTEM_RESET, $entry->event);
        $this->assertSame('Chefin', $entry->user_name);
        $this->assertSame(1, $entry->id);

        // Andere Bereiche bleiben unberührt.
        $this->assertTrue($product->exists && Product::query()->whereKey($product->id)->exists());
    }

    public function test_admin_page_lists_and_filters_entries(): void
    {
        $this->actingAs(User::factory()->create());

        app(ActivityLogger::class)->log(ActivityLog::ITEM_CANCELLED, 'Tisch 3: 1× Bier storniert');
        app(ActivityLogger::class)->log(ActivityLog::PAYMENT_CREATED, 'Tisch 3: 5,00 € Bar');

        $this->get(route('admin.activity-log'))->assertOk();

        Livewire::test(ActivityLogIndex::class)
            ->assertSee('Tisch 3: 1× Bier storniert')
            ->assertSee('Tisch 3: 5,00 € Bar')
            ->set('event', ActivityLog::PAYMENT_CREATED)
            ->assertDontSee('Bier storniert')
            ->assertSee('5,00 € Bar')
            ->set('event', '')
            ->set('search', 'BIER')
            ->assertSee('Bier storniert')
            ->assertDontSee('5,00 € Bar');
    }
}
