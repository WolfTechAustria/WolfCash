<?php

namespace App\Livewire\Admin\Products;

use Livewire\Component;
use App\Models\Product;

class Index extends Component
{
    public string $name = '';
    public string $category = '';
    public string $price = '';

    protected function rules(): array
    {
        return [
            'name' => 'required',
            'category' => 'required',
            'price' => 'required|numeric|min:0',
        ];
    }

    public function save()
    {
        $this->validate();

        Product::create([
            'name' => $this->name,
            'category' => $this->category,
            'price' => $this->price,
        ]);

        $this->reset([
            'name',
            'category',
            'price',
        ]);
    }

    public function delete(int $id)
    {
        Product::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.products.index', [
            'products' => Product::orderBy('category')
                ->orderBy('name')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
