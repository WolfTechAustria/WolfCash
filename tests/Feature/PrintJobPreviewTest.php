<?php

namespace Tests\Feature;

use App\Livewire\Admin\PrintJobs\Index;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrintJobPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_rows_are_collapsed_and_expand_to_paper_preview(): void
    {
        $table = Table::create(['number' => '7', 'name' => 'Tisch 7']);
        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_PAID]);
        $printer = Printer::create(['name' => 'Küche']);

        $production = PrintJob::create([
            'order_id' => $order->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_PRODUCTION,
            'payload' => ['items' => [
                ['name' => 'Schnitzel', 'quantity' => 2, 'note' => 'ohne Pommes'],
            ]],
        ]);

        $receipt = PrintJob::create([
            'order_id' => $order->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'payload' => [
                'amount' => 12.5,
                'payment_method' => 'cash',
                'items' => [
                    ['name' => 'Bier', 'quantity' => 2, 'unit_price' => 6.25],
                ],
            ],
        ]);

        Livewire::test(Index::class)
            ->assertSee('2× Schnitzel')
            ->assertSee('12,50 €')
            ->assertDontSee('Produktionsbon')
            ->assertDontSee('Rohdaten (JSON)')
            ->call('toggle', $production->id)
            ->assertSee('Produktionsbon')
            ->assertSee('TISCH 7')
            ->assertSee('> ohne Pommes')
            ->call('toggle', $receipt->id)
            ->assertSee('GESAMT: 12,50 EUR')
            ->assertSee('Zahlungsart: Bar')
            ->call('toggle', $production->id)
            ->assertDontSee('Produktionsbon');
    }
}
