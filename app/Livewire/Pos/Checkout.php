<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Table;
use App\Services\PaymentService;
use Illuminate\Support\Collection;
use Livewire\Component;

class Checkout extends Component
{
    public Table $table;

    public ?Order $order = null;

    /**
     * OrderItem-ID => ausgewählte Menge
     *
     * @var array<int|string, int>
     */
    public array $selectedForPayment = [];

    public bool $paymentFinished = false;

    public ?Payment $lastPayment = null;

    public function mount(Table $table): void
    {
        $this->table = $table;

        $this->loadOrder();
    }

    public function loadOrder(): void
    {
        $this->order = Order::query()
            ->with([
                'items.product',
                'payments',
            ])
            ->where('table_id', $this->table->id)
            ->where('status', Order::STATUS_OPEN)
            ->first();

        /*
         * Auswahl nach einem Reload bereinigen.
         */
        $this->sanitizeSelection();
    }

    public function getSelectedTotalProperty(): float
    {
        if (! $this->order) {
            return 0.0;
        }

        $total = 0.0;

        foreach ($this->selectedForPayment as $itemId => $quantity) {
            $item = $this->order->items
                ->firstWhere('id', (int) $itemId);

            if (! $item || $item->paid_at) {
                continue;
            }

            $payableQuantity = min(
                max(0, (int) $quantity),
                $item->open_quantity
            );

            $total += (float) $item->price
                * $payableQuantity;
        }

        return round($total, 2);
    }

    public function getOpenItemsProperty(): Collection
    {
        if (! $this->order) {
            return collect();
        }

        return $this->order->items
            ->filter(
                fn ($item) =>
                    $item->paid_at === null
                    && $item->open_quantity > 0
            )
            ->values();
    }

    public function addToPayment(int $itemId): void
    {
        $item = $this->openItems
            ->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        $current = (int) (
            $this->selectedForPayment[$itemId] ?? 0
        );

        /*
         * Stornierte Mengen dürfen nicht ausgewählt werden.
         */
        if ($current >= $item->open_quantity) {
            return;
        }

        $this->selectedForPayment[$itemId] =
            $current + 1;

        $this->resetErrorBag('payment');
    }

    public function removeFromPayment(int $itemId): void
    {
        if (! isset($this->selectedForPayment[$itemId])) {
            return;
        }

        $newQuantity =
            (int) $this->selectedForPayment[$itemId] - 1;

        if ($newQuantity <= 0) {
            unset($this->selectedForPayment[$itemId]);

            return;
        }

        $this->selectedForPayment[$itemId] =
            $newQuantity;
    }

    public function paySelected(
        string $method,
        PaymentService $paymentService
    ): void {
        if (! $this->order) {
            return;
        }

        $this->sanitizeSelection();

        if ($this->selectedForPayment === []) {
            $this->addError(
                'payment',
                'Bitte mindestens eine Position auswählen.'
            );

            return;
        }

        $paymentService->paySelection(
            $this->order,
            $this->selectedForPayment,
            $method
        );

        $this->selectedForPayment = [];

        $this->loadOrder();

        $this->closeOrderIfFullyPaid();
    }

    public function payOpen(
        string $method,
        PaymentService $paymentService
    ): void {
        if (! $this->order) {
            return;
        }

        $paymentService->payRemaining(
            $this->order,
            $method
        );

        $this->selectedForPayment = [];

        $this->loadOrder();

        $this->closeOrderIfFullyPaid();
    }

    public function getTotalAmountProperty(): float
    {
        if (! $this->order) {
            return 0.0;
        }

        /*
         * Ursprünglich verrechenbarer Gesamtbetrag:
         * stornierte Mengen werden nicht eingerechnet.
         */
        return round(
            $this->order->items->sum(
                fn ($item) =>
                    (float) $item->price
                    * $item->open_quantity
            ),
            2
        );
    }

    public function getPaidAmountProperty(): float
    {
        if (! $this->order) {
            return 0.0;
        }

        /*
         * Zahlungen sind die verlässlichste Quelle
         * für den bereits bezahlten Betrag.
         */
        return round(
            (float) $this->order->payments->sum('amount'),
            2
        );
    }

    public function getOpenAmountProperty(): float
    {
        return max(
            0.0,
            round(
                $this->totalAmount - $this->paidAmount,
                2
            )
        );
    }

    private function sanitizeSelection(): void
    {
        if (! $this->order) {
            $this->selectedForPayment = [];

            return;
        }

        $sanitized = [];

        foreach ($this->selectedForPayment as $itemId => $quantity) {
            $item = $this->order->items
                ->firstWhere('id', (int) $itemId);

            if (
                ! $item
                || $item->paid_at
                || $item->open_quantity <= 0
            ) {
                continue;
            }

            $quantity = min(
                max(0, (int) $quantity),
                $item->open_quantity
            );

            if ($quantity > 0) {
                $sanitized[(int) $itemId] = $quantity;
            }
        }

        $this->selectedForPayment = $sanitized;
    }

    private function closeOrderIfFullyPaid(): void
    {
        if (! $this->order) {
            return;
        }

        /*
         * Nicht anhand der Anzahl der Datensätze prüfen.
         * Vollständig stornierte, unbezahlte Datensätze dürfen
         * das Schließen der Bestellung nicht verhindern.
         */
        if ($this->openAmount > 0.009) {
            return;
        }

        $this->order->update([
            'status' => Order::STATUS_PAID,
        ]);

        $this->table->update([
            'status' => 'free',
        ]);

        $this->lastPayment = $this->order
            ->payments()
            ->latest()
            ->first();

        $this->paymentFinished = true;
    }

    public function backToPos()
    {
        return redirect()->route('pos.index');
    }

    public function render()
    {
        return view('livewire.pos.checkout')
            ->layout('components.layouts.app');
    }
}
