<?php

namespace Tests\Feature;

use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintOutput;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Printing\PrintOutputRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReceiptSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function receiptOutput(): PrintOutput
    {
        $table = Table::create(['number' => '5', 'name' => 'Tisch 5']);
        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_PAID]);
        $printer = Printer::create(['name' => 'Kassa', 'is_active' => true]);

        $payload = [
            'receipt_number' => 1,
            'order_id' => $order->id,
            'table_number' => '5',
            'payment_method' => 'cash',
            'amount' => 9,
            'items' => [['name' => 'Bier', 'quantity' => 2, 'unit_price' => 4.5, 'total' => 9]],
        ];

        $job = PrintJob::create([
            'order_id' => $order->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_RECEIPT,
            'payload' => $payload,
        ]);

        return PrintOutput::create([
            'print_job_id' => $job->id,
            'printer_id' => $printer->id,
            'quantity' => 1,
            'type' => PrintOutput::TYPE_RECEIPT,
            'status' => 'pending',
            'payload' => $payload,
        ]);
    }

    public function test_receipt_uses_configured_title_and_intro(): void
    {
        Setting::putValue(Setting::RECEIPT_TITLE, 'FF Musterdorf');
        Setting::putValue(Setting::RECEIPT_INTRO, "Zeltfest 2026\nHauptstraße 1");

        $document = app(PrintOutputRenderer::class)->render($this->receiptOutput());

        $this->assertSame('FF Musterdorf', $document->title);
        $this->assertSame(['Zeltfest 2026', 'Hauptstraße 1', '', 'Zahlungsbeleg'], $document->headerLines);
        $this->assertNotContains('WOLFCASH', $document->lines);
    }

    public function test_receipt_without_title_falls_back_to_zahlungsbeleg(): void
    {
        $document = app(PrintOutputRenderer::class)->render($this->receiptOutput());

        $this->assertSame('Zahlungsbeleg', $document->title);
        $this->assertSame([], $document->headerLines);
        $this->assertNotContains('WOLFCASH', $document->lines);
    }

    public function test_admin_can_choose_printers_and_receipt_texts(): void
    {
        $this->actingAs(User::factory()->create());

        $receiptPrinter = Printer::create(['name' => 'Kassa', 'is_active' => true]);
        $bonPrinter = Printer::create(['name' => 'Bon-Drucker', 'is_active' => true]);

        Livewire::test(SettingsIndex::class)
            ->assertSee('Bon-Drucker der stationären Kassen')
            ->set('receiptPrinterId', $receiptPrinter->id)
            ->set('stationaryPrinterId', $bonPrinter->id)
            ->set('receiptTitle', 'FF Musterdorf')
            ->set('receiptIntro', 'Zeltfest 2026')
            ->assertSee('FF Musterdorf')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($receiptPrinter->id, Setting::receiptPrinterId());
        $this->assertSame($bonPrinter->id, Setting::stationaryPrinterId());
        $this->assertSame('FF Musterdorf', Setting::receiptTitle());
        $this->assertSame('Zeltfest 2026', Setting::receiptIntro());
    }

    public function test_unknown_printer_and_too_long_title_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SettingsIndex::class)
            ->set('receiptPrinterId', 999)
            ->set('receiptTitle', str_repeat('x', 33))
            ->call('save')
            ->assertHasErrors(['receiptPrinterId', 'receiptTitle']);

        $this->assertSame('', Setting::receiptTitle());
    }

    public function test_selecting_no_printer_is_allowed(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SettingsIndex::class)
            ->set('receiptPrinterId', '')
            ->set('stationaryPrinterId', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, Setting::stationaryPrinterId());
    }

    public function test_printer_ids_fall_back_to_env_config(): void
    {
        config([
            'printing.receipt_printer_id' => 7,
            'printing.stationary_order_printer_id' => 8,
        ]);

        $this->assertSame(7, Setting::receiptPrinterId());
        $this->assertSame(8, Setting::stationaryPrinterId());
    }
}
