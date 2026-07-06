<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Livewire\Component;

class Checkout extends Component
{
    public Table $table;

    public ?Order $order = null;

    public array $selectedForPayment = [];

    public function mount(Table $table): void
    {
        $this->table = $table;

        $this->loadOrder();
    }

    public function loadOrder(): void
    {
        $this->order = Order::with([
            'items.product',
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

    public function paySelected(string $method): void
    {
        if (! $this->order) {
            return;
        }

        if (count($this->selectedForPayment) === 0) {
            $this->addError('payment', 'Bitte mindestens eine Position auswählen.');
            return;
        }

        foreach ($this->selectedForPayment as $itemId => $quantity) {
            $item = OrderItem::where('order_id', $this->order->id)
                ->whereNull('paid_at')
                ->find($itemId);

            if (! $item) {
                continue;
            }

            $payQuantity = min((int) $quantity, $item->quantity);

            if ($payQuantity === $item->quantity) {
                $item->update([
                    'paid_at' => now(),
                ]);
            } else {
                OrderItem::create([
                    'order_id' => $item->order_id,
                    'product_id' => $item->product_id,
                    'quantity' => $payQuantity,
                    'price' => $item->price,
                    'note' => $item->note,
                    'status' => $item->status,
                    'paid_at' => now(),
                ]);

                $item->update([
                    'quantity' => $item->quantity - $payQuantity,
                ]);
            }
        }

        $this->selectedForPayment = [];

        $this->loadOrder();

        if ($this->openItems->count() === 0) {
            $this->order->update([
                'status' => Order::STATUS_PAID,
            ]);

            $this->table->update([
                'status' => 'free',
            ]);

            redirect()->route('pos.index');
        }
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

    public function payAll(string $method): void
    {
        if (! $this->order) {
            return;
        }

        OrderItem::where('order_id', $this->order->id)
            ->whereNull('paid_at')
            ->update([
                'paid_at' => now(),
            ]);

        $this->order->update([
            'status' => Order::STATUS_PAID,
        ]);

        $this->table->update([
            'status' => 'free',
        ]);

        redirect()->route('pos.index');
    }

    public function render()
    {
        return view('livewire.pos.checkout')
            ->layout('components.layouts.app');
    }
}
