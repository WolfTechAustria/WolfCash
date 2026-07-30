<?php

namespace App\Livewire\Pos;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductGroup;
use App\Models\Table;
use App\Services\OrderCancellationService;
use App\Services\OrderService;
use App\Services\PrintService;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Throwable;

class Index extends Component
{
    public ?int $selectedTable = null;

    public array $cart = [];

    public array $orderItems = [];

    public ?int $activeGroup = null;

    public ?int $activeCategory = null;

    public string $tableSelectionMode = 'keypad';

    public string $tableNumberInput = '';

    /*
     * Mobiler Warenkorb
     */
    public bool $cartOpen = false;

    /*
     * Storno-Modal
     */
    public bool $showCancellationModal = false;

    public ?int $cancellationItemId = null;

    public string $cancellationItemName = '';

    public int $cancellationOpenQuantity = 0;

    public int $cancellationQuantity = 1;

    public string $cancellationReason = '';

    public function selectTable(int $tableId): void
    {
        $this->selectedTable = $tableId;

        $this->cartOpen = false;
        $this->cart = [];
        $this->orderItems = [];

        $order = Order::query()
            ->where('table_id', $tableId)
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

    public function backToTables(): void
    {
        $this->cartOpen = false;
        $this->selectedTable = null;
        $this->cart = [];
        $this->orderItems = [];
    }

    public function openCart(): void
    {
        $this->cartOpen = true;
    }

    public function closeCart(): void
    {
        $this->cartOpen = false;
    }

    public function setTableSelectionMode(string $mode): void
    {
        if (! in_array($mode, ['keypad', 'list'], true)) {
            return;
        }

        $this->tableSelectionMode = $mode;
    }

    public function pressTableNumber(string $number): void
    {
        if (! ctype_digit($number)) {
            return;
        }

        $this->resetErrorBag('tableNumberInput');

        $this->tableNumberInput .= $number;
    }

    public function clearTableNumber(): void
    {
        $this->tableNumberInput = '';

        $this->resetErrorBag('tableNumberInput');
    }

    public function deleteLastTableNumber(): void
    {
        $this->tableNumberInput = substr(
            $this->tableNumberInput,
            0,
            -1
        );

        $this->resetErrorBag('tableNumberInput');
    }

    public function confirmTableNumber(): void
    {
        if ($this->tableNumberInput === '') {
            return;
        }

        $table = Table::query()
            ->where('number', $this->tableNumberInput)
            ->first();

        if (! $table) {
            $this->addError(
                'tableNumberInput',
                'Tisch nicht gefunden.'
            );

            return;
        }

        $this->selectTable($table->id);

        $this->tableNumberInput = '';
    }

    public function setGroup(int $groupId): void
    {
        $this->activeGroup = $groupId;

        $this->activeCategory = ProductCategory::query()
            ->where('product_group_id', $groupId)
            ->value('id');
    }

    public function setCategory(int $categoryId): void
    {
        $this->activeCategory = $categoryId;
    }

    public function addProduct(int $productId): void
    {
        $product = Product::query()
            ->findOrFail($productId);

        if ($product->isSoldOut()) {
            return;
        }

        if (! isset($this->cart[$productId])) {
            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => 0,
                'note' => '',
            ];
        }

        $this->cart[$productId]['quantity']++;
    }

    public function removeProduct(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['quantity']--;

        if ($this->cart[$productId]['quantity'] <= 0) {
            unset($this->cart[$productId]);
        }
    }

    public function increaseProduct(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['quantity']++;
    }

    public function bonieren(
        OrderService $orderService,
        PrintService $printService
    ): void {
        if (! $this->selectedTable) {
            return;
        }

        if ($this->cart === []) {
            return;
        }

        $tableId = $this->selectedTable;

        $orderService->createOrder(
            $tableId,
            $this->cart,
            $printService
        );

        /*
         * Warenkorb leeren und bestehende Positionen neu laden.
         */
        $this->cart = [];

        $this->selectTable($tableId);

        /*
         * Auf mobilen Geräten nach erfolgreicher Bonierung schließen.
         */
        $this->cartOpen = false;
    }

    public function getTotalProperty(): float
    {
        $total = 0.0;

        /*
         * Bereits bonierte, nicht stornierte Positionen.
         */
        foreach ($this->orderItems as $item) {
            $total +=
                (float) $item['price']
                * (int) $item['open_quantity'];
        }

        /*
         * Noch nicht bonierte Warenkorbpositionen.
         */
        foreach ($this->cart as $item) {
            $total +=
                (float) $item['price']
                * (int) $item['quantity'];
        }

        return round($total, 2);
    }

    public function openCancellationModal(int $itemId): void
    {
        $item = OrderItem::query()
            ->with('product')
            ->findOrFail($itemId);

        /*
         * Sicherheit: Nur Positionen des aktuell gewählten Tisches.
         */
        if (
            $this->selectedTable === null
            || $item->order?->table_id !== $this->selectedTable
        ) {
            return;
        }

        if ($item->open_quantity <= 0) {
            return;
        }

        /*
         * Warenkorb-Drawer schließen, damit das Modal alleine sichtbar ist.
         */
        $this->cartOpen = false;

        $this->cancellationItemId = $item->id;

        $this->cancellationItemName =
            $item->product?->name
            ?? 'Unbekanntes Produkt';

        $this->cancellationOpenQuantity =
            $item->open_quantity;

        $this->cancellationQuantity = 1;
        $this->cancellationReason = '';

        $this->resetValidation();

        $this->showCancellationModal = true;
    }

    public function increaseCancellationQuantity(): void
    {
        $this->cancellationQuantity = min(
            $this->cancellationQuantity + 1,
            $this->cancellationOpenQuantity
        );
    }

    public function decreaseCancellationQuantity(): void
    {
        $this->cancellationQuantity = max(
            1,
            $this->cancellationQuantity - 1
        );
    }

    public function selectCancellationReason(
        string $reason
    ): void {
        $this->cancellationReason = $reason;
    }

    public function closeCancellationModal(): void
    {
        $this->resetCancellationForm();
    }

    public function confirmCancellation(
        OrderCancellationService $cancellationService
    ): void {
        $validated = $this->validate([
            'cancellationItemId' => [
                'required',
                'integer',
                'exists:order_items,id',
            ],
            'cancellationQuantity' => [
                'required',
                'integer',
                'min:1',
                'max:'.$this->cancellationOpenQuantity,
            ],
            'cancellationReason' => [
                'required',
                'string',
                'max:255',
            ],
        ], [
            'cancellationQuantity.max' =>
                'Es können höchstens '
                .$this->cancellationOpenQuantity
                .' Stück storniert werden.',

            'cancellationReason.required' =>
                'Bitte einen Stornogrund angeben.',
        ]);

        try {
            $item = OrderItem::query()
                ->with('order')
                ->findOrFail(
                    $validated['cancellationItemId']
                );

            /*
             * Sicherheit: Storno nur am aktuell geöffneten Tisch.
             */
            if (
                $this->selectedTable === null
                || $item->order?->table_id !== $this->selectedTable
            ) {
                throw ValidationException::withMessages([
                    'cancellationReason' =>
                        'Die Position gehört nicht zum aktuell gewählten Tisch.',
                ]);
            }

            $cancellationService->cancel(
                item: $item,
                quantity: $validated['cancellationQuantity'],
                reason: $validated['cancellationReason'],
                userId: auth()->id(),
            );

            $selectedTable = $this->selectedTable;

            $this->resetCancellationForm();

            /*
             * Der Service kann bei Vollstorno die Bestellung schließen
             * und den Tisch freigeben.
             */
            $openOrderStillExists = Order::query()
                ->where('table_id', $selectedTable)
                ->where('status', Order::STATUS_OPEN)
                ->exists();

            if (
                $selectedTable !== null
                && $openOrderStillExists
            ) {
                $this->selectTable($selectedTable);
            } else {
                $this->backToTables();
            }

            session()->flash(
                'success',
                'Position wurde erfolgreich storniert.'
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'cancellationReason' =>
                    $exception->getMessage(),
            ]);
        }
    }

    private function resetCancellationForm(): void
    {
        $this->showCancellationModal = false;
        $this->cancellationItemId = null;
        $this->cancellationItemName = '';
        $this->cancellationOpenQuantity = 0;
        $this->cancellationQuantity = 1;
        $this->cancellationReason = '';

        $this->resetValidation();
    }

    public function render()
    {
        if (! $this->activeGroup) {
            $firstGroup = ProductGroup::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->first();

            if ($firstGroup) {
                $this->activeGroup = $firstGroup->id;

                $this->activeCategory = ProductCategory::query()
                    ->where(
                        'product_group_id',
                        $firstGroup->id
                    )
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->value('id');
            }
        }

        return view('livewire.pos.index', [
            'tables' => Table::query()
                ->with('openOrder')
                ->orderBy('number')
                ->get(),

            'table' => $this->selectedTable
                ? Table::query()->find($this->selectedTable)
                : null,

            'groups' => ProductGroup::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),

            'categories' => $this->activeGroup
                ? ProductCategory::query()
                    ->where(
                        'product_group_id',
                        $this->activeGroup
                    )
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
                : collect(),

            'products' => $this->activeCategory
                ? Product::query()
                    ->where(
                        'product_category_id',
                        $this->activeCategory
                    )
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get()
                : collect(),
        ])->layout('components.layouts.app');
    }
}
