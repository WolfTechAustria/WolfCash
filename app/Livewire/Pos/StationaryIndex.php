<?php

namespace App\Livewire\Pos;

use App\Livewire\Pos\Concerns\ManagesProductCart;
use App\Models\Order;
use App\Models\Table;
use App\Services\OrderService;
use App\Services\PrintService;
use Livewire\Component;

class StationaryIndex extends Component
{
    use ManagesProductCart;

    public Table $table;

    /*
     * Mobiler Warenkorb
     */
    public bool $cartOpen = false;

    /*
     * Statt eines gebündelten Bons pro Artikel wird für
     * jede einzelne Einheit ein eigener Bon gedruckt
     * (z. B. 20x Schnitzel -> 20 Bons á 1 Schnitzel).
     */
    public bool $printIndividually = false;

    public function mount(Table $table): void
    {
        abort_unless($table->is_stationary, 404);

        $this->table = $table;

        $this->loadOrderItems();
    }

    public function loadOrderItems(): void
    {
        $this->orderItems = [];

        $order = Order::query()
            ->where('table_id', $this->table->id)
            ->where('status', Order::STATUS_OPEN)
            ->first();

        if (! $order) {
            return;
        }

        foreach ($order->items()->with('product')->get() as $item) {
            $this->orderItems[] = [
                'id' => $item->id,
                'name' => $item->product?->name
                    ?? 'Unbekanntes Produkt',
                'quantity' => $item->quantity,
                'cancelled_quantity' => $item->cancelled_quantity,
                'open_quantity' => $item->open_quantity,
                'is_fully_cancelled' => $item->is_fully_cancelled,
                'price' => $item->price,
                'note' => $item->note,
            ];
        }
    }

    public function openCart(): void
    {
        $this->cartOpen = true;
    }

    public function closeCart(): void
    {
        $this->cartOpen = false;
    }

    public function bonieren(
        OrderService $orderService,
        PrintService $printService
    ): void {
        if ($this->cart === []) {
            return;
        }

        $orderService->createOrder(
            tableId: $this->table->id,
            cart: $this->cart,
            printService: null,
            source: Order::SOURCE_STATIONARY,
            onOrderCreated: fn (Order $order, array $createdItems) =>
                $printService->createStationaryOrderJob(
                    $order,
                    $createdItems,
                    $this->printIndividually
                ),
        );

        /*
         * Warenkorb leeren und bestehende Positionen neu laden.
         * Einzeldruck ist bewusst nur für diesen Bonierungsvorgang
         * gültig und wird danach zurückgesetzt.
         */
        $this->cart = [];
        $this->printIndividually = false;

        $this->loadOrderItems();

        /*
         * Auf mobilen Geräten nach erfolgreicher Bonierung schließen.
         */
        $this->cartOpen = false;
    }

    public function render()
    {
        return view(
            'livewire.pos.stationary',
            $this->loadProductCatalog()
        )->layout('components.layouts.app');
    }
}
