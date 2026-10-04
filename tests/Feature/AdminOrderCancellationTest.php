<?php

namespace Tests\Feature;

use App\Livewire\Admin\Orders\Show;
use App\Models\ActivityLog;
use App\Models\DailyClosing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintOutput;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Setting;
use App\Models\Table;
use App\Models\User;
use App\Printing\PrintOutputRenderer;
use App\Services\OrderCancellationService;
use App\Services\PaymentReceiptService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOrderCancellationTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private OrderItem $item;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Setting::putValue(Setting::RECEIPT_AUTOMATIC_PRINTING_ENABLED, false);

        $group = ProductGroup::create(['name' => 'Getränke']);
        $category = ProductCategory::create(['product_group_id' => $group->id, 'name' => 'Bier']);
        $table = Table::create(['number' => '3']);

        $this->product = Product::create([
            'name' => 'Bier',
            'price' => 4.5,
            'available_quantity' => 10,
            'is_active' => true,
            'product_category_id' => $category->id,
        ]);

        $this->order = Order::create(['table_id' => $table->id, 'status' => Order::STATUS_OPEN]);

        $this->item = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $this->product->id,
            'quantity' => 3,
            'price' => 4.5,
        ]);

        $this->order->recalculateTotal();

        $this->actingAs(User::factory()->create());
    }

    public function test_admin_can_cancel_part_of_a_paid_position(): void
    {
        app(PaymentService::class)->payRemaining($this->order, 'cash');

        $this->assertSame(Order::STATUS_PAID, $this->order->fresh()->status);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openCancellation', $this->item->id)
            ->assertSet('cancellationQuantity', 3)
            ->assertSee('bereits bezahlt')
            ->set('cancellationQuantity', 2)
            ->set('cancellationReason', 'Falsch boniert')
            ->call('confirmCancellation')
            ->assertHasNoErrors()
            ->assertSet('cancellationItemId', null)
            ->assertSee('2× Bier wurde storniert.');

        $item = $this->item->fresh();
        $this->assertSame(2, $item->cancelled_quantity);
        $this->assertSame('Falsch boniert', $item->cancellations()->first()->reason);

        $this->assertEquals(4.5, (float) $this->order->fresh()->total);
        $this->assertSame(12, $this->product->fresh()->available_quantity);
        $this->assertSame(1, ActivityLog::query()->where('event', ActivityLog::ITEM_CANCELLED)->count());

        // Zahlung und gespeicherter Beleg sind um das Storno reduziert.
        $payment = Payment::query()->sole();
        $this->assertEquals(4.5, (float) $payment->amount);

        $payload = $payment->receiptPrintJob->payload;
        $this->assertEquals(4.5, $payload['amount']);
        $this->assertSame(1, $payload['items'][0]['quantity']);
        $this->assertEquals(4.5, $payload['items'][0]['total']);
        $this->assertSame(2, $payload['cancellations'][0]['quantity']);
        $this->assertNotNull($payload['corrected_at']);
    }

    public function test_reprinted_receipt_shows_corrected_amount_and_cancellation(): void
    {
        $printer = Printer::create(['name' => 'Kassa', 'is_active' => true]);
        Setting::putValue(Setting::RECEIPT_PRINTER_ID, $printer->id);

        $payment = app(PaymentService::class)->payRemaining($this->order, 'cash');

        app(OrderCancellationService::class)->cancel($this->item->fresh(), 3, 'Retour');

        $output = app(PaymentReceiptService::class)->reprint($payment->fresh());
        $lines = array_map('strval', app(PrintOutputRenderer::class)->render($output)->lines);

        // Stornierte Positionen werden nicht gedruckt, nur der korrigierte Betrag.
        $this->assertNotContains('3x Bier', $lines);
        $this->assertContains('GESAMT: 0,00 EUR', $lines);
        $this->assertNotEmpty(preg_grep('/^Korrigiert: /', $lines));
        $this->assertEquals(0, (float) $payment->fresh()->amount);
    }

    public function test_split_payment_line_is_matched_by_product(): void
    {
        // Teilzahlung spaltet eine neue bezahlte Position ab; der Beleg
        // verweist aber auf die ursprüngliche Position.
        $payment = app(PaymentService::class)->paySelection(
            $this->order,
            [$this->item->id => 2],
            'cash'
        );

        $paidItem = OrderItem::query()->where('payment_id', $payment->id)->sole();
        $this->assertNotSame($this->item->id, $paidItem->id);

        app(OrderCancellationService::class)->cancel($paidItem, 1, 'Falsch');

        $payload = $payment->fresh()->receiptPrintJob->payload;
        $this->assertSame(1, $payload['items'][0]['quantity']);
        $this->assertEquals(4.5, (float) $payment->fresh()->amount);
    }

    public function test_legacy_paid_position_without_payment_id_is_corrected(): void
    {
        $payment = app(PaymentService::class)->payRemaining($this->order, 'cash');

        // Vor Einführung von order_items.payment_id bezahlte Positionen.
        $this->item->update(['payment_id' => null]);

        app(OrderCancellationService::class)->cancel($this->item->fresh(), 1, 'Altbestand');

        $payment->refresh();
        $this->assertEquals(9.0, (float) $payment->amount);
        $this->assertSame(2, $payment->receiptPrintJob->payload['items'][0]['quantity']);
        $this->assertEquals(9.0, $payment->receiptPrintJob->payload['amount']);
    }

    public function test_paid_position_of_closed_day_cannot_be_cancelled(): void
    {
        $this->travelTo(now()->subDay());
        app(PaymentService::class)->payRemaining($this->order, 'cash');
        DailyClosing::create(['business_date' => today(), 'closed_at' => now(), 'snapshot' => []]);
        $this->travelBack();

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openCancellation', $this->item->id)
            ->set('cancellationReason', 'Zu spät')
            ->call('confirmCancellation')
            ->assertHasErrors(['cancellationReason'])
            ->assertSee('bereits abgeschlossen');

        $this->assertSame(0, $this->item->fresh()->cancelled_quantity);
        $this->assertEquals(13.5, (float) Payment::query()->sole()->amount);
    }

    public function test_quantity_above_open_amount_and_missing_reason_are_rejected(): void
    {
        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openCancellation', $this->item->id)
            ->set('cancellationQuantity', 4)
            ->set('cancellationReason', '')
            ->call('confirmCancellation')
            ->assertHasErrors(['cancellationQuantity', 'cancellationReason']);

        $this->assertSame(0, $this->item->fresh()->cancelled_quantity);
    }

    public function test_fully_cancelled_position_has_no_cancel_button(): void
    {
        $this->item->update(['cancelled_quantity' => 3]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->assertDontSee('Stornieren')
            ->call('openCancellation', $this->item->id)
            ->assertSet('cancellationItemId', null);
    }

    public function test_kitchen_ticket_is_only_printed_when_requested(): void
    {
        $printer = Printer::create(['name' => 'Schank']);

        PrintJob::create([
            'order_id' => $this->order->id,
            'printer_id' => $printer->id,
            'type' => PrintJob::TYPE_PRODUCTION,
            'status' => PrintJob::STATUS_PRINTED,
            'payload' => ['items' => [['order_item_id' => $this->item->id, 'name' => 'Bier', 'quantity' => 3]]],
        ]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openCancellation', $this->item->id)
            ->set('cancellationQuantity', 1)
            ->set('cancellationReason', 'Nachträglich')
            ->call('confirmCancellation')
            ->assertHasNoErrors();

        $this->assertSame(0, PrintOutput::query()->where('type', PrintOutput::TYPE_CANCELLATION)->count());

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openCancellation', $this->item->id)
            ->set('cancellationQuantity', 1)
            ->set('cancellationReason', 'Doch noch in der Küche')
            ->set('cancellationPrintTicket', true)
            ->call('confirmCancellation')
            ->assertHasNoErrors();

        $this->assertSame(1, PrintOutput::query()->where('type', PrintOutput::TYPE_CANCELLATION)->count());
    }

    public function test_closed_business_day_shows_error(): void
    {
        DailyClosing::create(['business_date' => today(), 'closed_at' => now(), 'snapshot' => []]);

        Livewire::test(Show::class, ['order' => $this->order])
            ->call('openCancellation', $this->item->id)
            ->set('cancellationReason', 'Zu spät')
            ->call('confirmCancellation')
            ->assertHasErrors(['cancellationReason']);

        $this->assertSame(0, $this->item->fresh()->cancelled_quantity);
    }
}
