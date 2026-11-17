<?php

namespace Tests\Feature;

use App\Livewire\Admin\Users\Index as UsersIndex;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'Anna Admin']);
        $this->actingAs($this->admin);
    }

    public function test_page_lists_users_and_is_in_menu(): void
    {
        User::factory()->create(['name' => 'Ben Kassa', 'email' => 'ben@example.com']);

        $this->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Ben Kassa')
            ->assertSee('ben@example.com')
            ->assertSee(route('admin.users'));
    }

    public function test_admin_can_create_user_who_can_log_in(): void
    {
        Livewire::test(UsersIndex::class)
            ->call('create')
            ->set('name', 'Clara Kellner')
            ->set('email', '  Clara@Example.com ')
            ->set('password', 'geheim1234')
            ->set('password_confirmation', 'geheim1234')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Clara Kellner wurde angelegt.');

        $user = User::query()->where('email', 'clara@example.com')->sole();
        $this->assertTrue(Hash::check('geheim1234', $user->password));
        $this->assertSame(1, ActivityLog::query()->where('event', ActivityLog::USER_CREATED)->count());
    }

    public function test_validation_on_create(): void
    {
        Livewire::test(UsersIndex::class)
            ->call('create')
            ->set('name', '')
            ->set('email', $this->admin->email)
            ->set('password', 'kurz')
            ->set('password_confirmation', 'anders')
            ->call('save')
            ->assertHasErrors(['name', 'email', 'password']);

        $this->assertSame(1, User::count());
    }

    public function test_edit_keeps_password_when_left_empty(): void
    {
        $user = User::factory()->create(['password' => 'altespasswort']);

        Livewire::test(UsersIndex::class)
            ->call('edit', $user->id)
            ->assertSet('email', $user->email)
            ->set('name', 'Neuer Name')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('Neuer Name', $user->name);
        $this->assertTrue(Hash::check('altespasswort', $user->password));
    }

    public function test_edit_can_change_password(): void
    {
        $user = User::factory()->create();

        Livewire::test(UsersIndex::class)
            ->call('edit', $user->id)
            ->set('password', 'neuespasswort')
            ->set('password_confirmation', 'neuespasswort')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('neuespasswort', $user->fresh()->password));
        $this->assertSame(
            ['password'],
            ActivityLog::query()->where('event', ActivityLog::USER_UPDATED)->sole()->properties['changed']
        );
    }

    public function test_user_with_payments_can_be_deleted(): void
    {
        $user = User::factory()->create(['name' => 'Ben Kassa']);

        $order = Order::create(['table_id' => Table::create(['number' => '1'])->id, 'status' => Order::STATUS_PAID]);
        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 10,
            'payment_method' => Payment::CASH,
            'user_id' => $user->id,
        ]);

        Livewire::test(UsersIndex::class)
            ->call('delete', $user->id)
            ->assertHasNoErrors()
            ->assertSee('Ben Kassa wurde gelöscht.');

        $this->assertNull(User::find($user->id));
        $this->assertNull($payment->fresh()->user_id);
        $this->assertSame(1, ActivityLog::query()->where('event', ActivityLog::USER_DELETED)->count());
    }

    public function test_cannot_delete_yourself(): void
    {
        User::factory()->create();

        Livewire::test(UsersIndex::class)
            ->call('delete', $this->admin->id)
            ->assertHasErrors(['delete']);

        $this->assertNotNull($this->admin->fresh());
    }
}
