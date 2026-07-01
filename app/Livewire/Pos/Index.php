<?php

namespace App\Livewire\Pos;

use Livewire\Component;
use App\Models\Table;
use App\Models\Product;
use App\Services\OrderService;
use App\Models\ProductGroup;
use App\Models\ProductCategory;

class Index extends Component
{
    public ?int $selectedTable = null;
    public array $cart = [];
    public array $orderItems = [];

    public ?int $activeGroup = null;

    public ?int $activeCategory = null;


    /*
    public function selectTable(int $tableId): void
    {
        $this->selectedTable = $tableId;
    }
    */

    public function selectTable(int $tableId): void
    {
        $this->selectedTable = $tableId;

        $this->cart = [];
        $this->orderItems = [];

        $order = \App\Models\Order::where('table_id',$tableId)
            ->where('status',\App\Models\Order::STATUS_OPEN)
            ->first();

        if (!$order) {
            return;
        }

        foreach ($order->items as $item) {

            $this->orderItems[] = [
                'name' => $item->product->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ];
        }
    }

    public function backToTables(): void
    {
        $this->selectedTable = null;
    }

    public function setGroup(int $groupId): void
    {
        $this->activeGroup = $groupId;

        $this->activeCategory = ProductCategory::where(
            'product_group_id',
            $groupId
        )->value('id');
    }

    public function setCategory(string $categoryId): void
    {
        $this->activeCategory = $categoryId;
    }

    public function addProduct(int $productId): void
    {
        $product = Product::findOrFail($productId);

        if (!isset($this->cart[$productId])) {

            $this->cart[$productId] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => 0,
            ];
        }

        $this->cart[$productId]['quantity']++;
    }

    public function removeProduct(int $productId): void
    {
        if (!isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['quantity']--;

        if ($this->cart[$productId]['quantity'] <= 0) {
            unset($this->cart[$productId]);
        }
    }

    public function increaseProduct(int $productId): void
    {
        if (!isset($this->cart[$productId])) {
            return;
        }

        $this->cart[$productId]['quantity']++;
    }

    public function bonieren(OrderService $orderService): void
    {

        if (!$this->selectedTable) {
            return;
        }

        if (count($this->cart) === 0) {
            return;
        }

        $orderService->createOrder(
            $this->selectedTable,
            $this->cart
        );

        $this->selectTable($this->selectedTable);
        $this->cart = [];
    }

    public function getTotalProperty(): float
    {
        $total = 0;

        foreach ($this->cart as $item) {

            $total += (
                $item['price']
                * $item['quantity']
            );
        }

        return $total;
    }

    public function render()
    {
        if (!$this->activeGroup) {

            $firstGroup = ProductGroup::first();

            if ($firstGroup) {

                $this->activeGroup = $firstGroup->id;

                $this->activeCategory = ProductCategory::where(
                    'product_group_id',
                    $firstGroup->id
                )->value('id');
            }
        }

        return view('livewire.pos.index', [

            'tables' => Table::orderBy('number')->get(),

            'table' => $this->selectedTable
                ? Table::find($this->selectedTable)
                : null,

            'groups' => ProductGroup::orderBy('sort_order')
                ->orderBy('name')
                ->get(),

            'categories' => $this->activeGroup
                ? ProductCategory::where(
                    'product_group_id',
                    $this->activeGroup
                )->orderBy('sort_order')
                    ->orderBy('name')->get()
                : collect(),

            'products' => $this->activeCategory
                ? Product::where(
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
