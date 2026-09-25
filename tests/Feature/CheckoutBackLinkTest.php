<?php

namespace Tests\Feature;

use App\Livewire\Pos\Checkout;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CheckoutBackLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_stationary_checkout_returns_to_its_own_till(): void
    {
        $table = Table::create(['number' => 'ST-1', 'name' => 'Kasse 1', 'is_stationary' => true]);

        Livewire::test(Checkout::class, ['table' => $table])
            ->assertSeeHtml('href="/pos/stationary/'.$table->id.'"')
            ->call('backToPos')
            ->assertRedirect('/pos/stationary/'.$table->id);
    }

    public function test_waiter_checkout_returns_to_table_selection(): void
    {
        $table = Table::create(['number' => '1', 'name' => 'Tisch 1']);

        Livewire::test(Checkout::class, ['table' => $table])
            ->assertSeeHtml('href="/pos"')
            ->call('backToPos')
            ->assertRedirect('/pos');
    }
}
