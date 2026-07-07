<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Livewire\Component;
use App\Services\PaymentService;
use App\Models\Payment;

class Checkout extends Component
{
    public Table $table;

    public ?Order $order = null;

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
        $this->order = Order::with([
            'items.product',
            'payments',
        ])
            ->where('table_id', $this->table->id)
            ->where('status', Order::STATUS_OPEN)
            ->first();
    }

    public function getSelectedTotalProperty(): float
    {
        if (! $this->order) {
            return 0;
        }

        $total = 0;

        foreach ($this->selectedForPayment as $itemId => $quantity) {
            $item = $this->order->items->firstWhere('id', (int) $itemId);

            if (! $item || $item->paid_at) {
                continue;
            }

            $total += $item->price * $quantity;
        }

        return $total;
    }

    public function getOpenItemsProperty()
    {
        if (! $this->order) {
            return collect();
        }

        return $this->order->items
            ->whereNull('paid_at');
    }

    public function paySelected(string $method,PaymentService $paymentService): void
    {
        if (! $this->order) {
            return;
        }

        if (count($this->selectedForPayment) === 0) {
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

    public function payOpen(string $method,PaymentService $paymentService): void
    {
        if (! $this->order) {
            return;
        }

        $paymentService->payRemaining(
            $this->order,
            $method
        );

        $this->loadOrder();

        $this->closeOrderIfFullyPaid();
    }

    public function addToPayment(int $itemId): void
    {
        $item = $this->openItems->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        $current = $this->selectedForPayment[$itemId] ?? 0;

        if ($current >= $item->quantity) {
            return;
        }

        $this->selectedForPayment[$itemId] = $current + 1;
    }

    public function removeFromPayment(int $itemId): void
    {
        if (! isset($this->selectedForPayment[$itemId])) {
            return;
        }

        $this->selectedForPayment[$itemId]--;

        if ($this->selectedForPayment[$itemId] <= 0) {
            unset($this->selectedForPayment[$itemId]);
        }
    }

    public function getTotalAmountProperty(): float
    {
        if (! $this->order) {
            return 0;
        }

        return $this->order->items
            ->sum(fn ($item) => $item->price * $item->quantity);
    }

    public function getPaidAmountProperty(): float
    {
        if (! $this->order) {
            return 0;
        }

        return $this->order->items
            ->whereNotNull('paid_at')
            ->sum(fn ($item) => $item->price * $item->quantity);
    }

    public function getOpenAmountProperty(): float
    {
        if (! $this->order) {
            return 0;
        }

        return $this->order->items
            ->whereNull('paid_at')
            ->sum(fn ($item) => $item->price * $item->quantity);
    }

    private function closeOrderIfFullyPaid(): void
    {
        if (! $this->order) {
            return;
        }

        if ($this->openItems->count() > 0) {
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
