<?php

namespace Tests\Feature;

use App\Enums\PrintTextSize;
use App\Livewire\Admin\Settings\Index as SettingsIndex;
use App\Models\Order;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Printing\PrintLine;
use App\Printing\ProductionTicketRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrintTextSizeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string|PrintLine>
     */
    private function productionLines(string $type = PrintJob::TYPE_PRODUCTION): array
    {
        $table = Table::create(['number' => '7']);
        $order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_OPEN]);
        $printer = Printer::create(['name' => 'Küche']);

        $job = PrintJob::create([
            'order_id' => $order->id,
            'printer_id' => $printer->id,
            'type' => $type,
            'payload' => ['items' => [['name' => 'Gulasch', 'quantity' => 2]]],
        ]);

        return app(ProductionTicketRenderer::class)->render($job)[0]->lines;
    }

    private function lineWithText(array $lines, string $text): PrintLine
    {
        $line = collect($lines)->first(fn ($line) => (string) $line === $text);

        $this->assertInstanceOf(PrintLine::class, $line);

        return $line;
    }

    public function test_sizes_default_to_normal(): void
    {
        $lines = $this->productionLines();

        $this->assertSame(PrintTextSize::Normal, $this->lineWithText($lines, 'TISCH 7')->size);
        $this->assertSame(PrintTextSize::Normal, $this->lineWithText($lines, '2x Gulasch')->size);
    }

    public function test_table_and_product_sizes_are_applied_separately(): void
    {
        Setting::putValue(Setting::PRINT_TABLE_TEXT_SIZE, PrintTextSize::Triple->value);
        Setting::putValue(Setting::PRINT_PRODUCT_TEXT_SIZE, PrintTextSize::Tall->value);

        $lines = $this->productionLines();

        $this->assertSame(PrintTextSize::Triple, $this->lineWithText($lines, 'TISCH 7')->size);
        $this->assertSame(PrintTextSize::Tall, $this->lineWithText($lines, '2x Gulasch')->size);
        $this->assertSame('Bon #'.PrintJob::query()->value('id'), (string) end($lines));
    }

    public function test_stationary_ticket_uses_product_size_without_bold(): void
    {
        Setting::putValue(Setting::PRINT_PRODUCT_TEXT_SIZE, PrintTextSize::Double->value);

        $article = $this->lineWithText(
            $this->productionLines(PrintJob::TYPE_STATIONARY_ORDER),
            '2x Gulasch'
        );

        $this->assertSame(PrintTextSize::Double, $article->size);
        $this->assertFalse($article->bold);
    }

    public function test_unknown_stored_value_falls_back_to_normal(): void
    {
        Setting::putValue(Setting::PRINT_TABLE_TEXT_SIZE, 'gigantisch');

        $this->assertSame(PrintTextSize::Normal, Setting::printTableTextSize());
    }

    public function test_admin_can_change_sizes(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SettingsIndex::class)
            ->assertSee('Schriftgröße am Bon')
            ->set('printTableTextSize', PrintTextSize::Double->value)
            ->set('printProductTextSize', PrintTextSize::Tall->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(PrintTextSize::Double, Setting::printTableTextSize());
        $this->assertSame(PrintTextSize::Tall, Setting::printProductTextSize());
    }

    public function test_invalid_size_is_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(SettingsIndex::class)
            ->set('printTableTextSize', 'gigantisch')
            ->call('save')
            ->assertHasErrors(['printTableTextSize']);

        $this->assertNull(Setting::query()->where('key', Setting::PRINT_TABLE_TEXT_SIZE)->value('value'));
    }
}
