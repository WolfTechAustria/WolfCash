<?php

namespace App\Livewire\Pos;

use Livewire\Component;
use App\Models\Table;
use App\Models\Product;

class Index extends Component
{
    public ?int $selectedTable = null;
    public array $cart = [];

    public function selectTable(int $tableId): void
    {
        $this->selectedTable = $tableId;
    }

    public function backToTables(): void
    {
        $this->selectedTable = null;
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
        return view('livewire.pos.index', [

            'tables' => Table::orderBy('number')->get(),

            'table' => $this->selectedTable
                ? Table::find($this->selectedTable)
                : null,

            'products' => Product::where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get(),

        ])->layout('components.layouts.app');
    }
}
