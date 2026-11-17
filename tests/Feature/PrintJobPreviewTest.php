<?php

namespace Tests\Feature;

use App\Livewire\Admin\PrintJobs\Index;
use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Printer;
use App\Models\PrintOutput;
use App\Models\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    public function test_reprint_moves_receipt_job_to_top_and_is_listed(): void
    {
        Queue::fake();

        $table = Table::create(['number' => '7']);
        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_PAID]);
        $printer = Printer::create(['name' => 'Kassa', 'is_active' => true]);

        $this->travelTo(now()->subHour());

        $receipt = PrintJob::create([
            'order_id' => $order->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'status' => PrintJob::STATUS_PRINTED,
            'payload' => ['amount' => 12.5, 'payment_method' => 'cash', 'items' => []],
        ]);

        PrintOutput::create([
            'print_job_id' => $receipt->id,
            'printer_id' => $printer->id,
            'quantity' => 1,
            'type' => PrintOutput::TYPE_RECEIPT,
            'status' => PrintOutput::STATUS_PRINTED,
            'payload' => ['manual_print' => false],
        ]);

        $this->travelBack();

        // Später angelegter Job, der ohne Nachdruck oben stünde.
        PrintJob::create([
            'order_id' => $order->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_PRODUCTION,
            'payload' => ['items' => [['name' => 'Schnitzel', 'quantity' => 1]]],
        ]);

        $this->travel(1)->minutes();

        PrintOutput::create([
            'print_job_id' => $receipt->id,
            'printer_id' => $printer->id,
            'quantity' => 1,
            'type' => PrintOutput::TYPE_RECEIPT,
            'status' => PrintOutput::STATUS_PENDING,
            'payload' => ['manual_print' => true, 'is_copy' => true],
        ]);

        Livewire::test(Index::class)
            ->assertSeeInOrder(['12,50 €', 'Schnitzel'])
            ->assertSee('Belegkopie')
            ->assertSee('2 Ausdrucke')
            ->assertSee('ausstehend')
            ->call('toggle', $receipt->id)
            ->assertSee('Automatischer Druck')
            ->assertSee('Ausdrucke');
    }
}
