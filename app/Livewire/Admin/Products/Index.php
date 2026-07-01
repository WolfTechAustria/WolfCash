<?php

namespace App\Livewire\Admin\Products;

use Livewire\Component;
use App\Models\Product;
use App\Models\ProductCategory;

class Index extends Component
{
    public string $name = '';

    public ?int $product_category_id = null;

    public string $price = '';

    public string $print_mode = 'grouped';

    public int $available_quantity = -1;

    public bool $is_active = true;

    public ?int $editingId = null;

    public string $search = '';
    public int $sort_order = 0;

    protected function rules(): array
    {
        return [
            'name' => 'required',

            'product_category_id' => 'required|exists:product_categories,id',

            'price' => 'required|numeric|min:0',

            'print_mode' => 'required',

            'available_quantity' => 'required|integer|min:-1',

            'sort_order' => 'required|integer|min:0',
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        $data = [
            'name' => $this->name,
            'product_category_id' => $this->product_category_id,
            'price' => $this->price,
            'print_mode' => $this->print_mode,
            'available_quantity' => $this->available_quantity,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];

        if ($this->editingId) {
            Product::findOrFail($this->editingId)->update($data);
        } else {
            Product::create($data);
        }

        $this->reset([
            'name',
            'product_category_id',
            'price',
            'editingId',
        ]);

        $this->print_mode = 'grouped';
        $this->available_quantity = -1;
        $this->is_active = true;
        $this->sort_order = 0;
    }

    public function delete(int $id)
    {
        Product::findOrFail($id)->delete();
    }

    public function toggleActive(int $id): void
    {
        $product = Product::findOrFail($id);

        $product->update([
            'is_active' => ! $product->is_active,
        ]);
    }

    public function edit(int $id): void
    {
        $product = Product::findOrFail($id);

        $this->editingId = $product->id;

        $this->name = $product->name;

        $this->product_category_id = $product->product_category_id;

        $this->price = $product->price;

        $this->print_mode = $product->print_mode;

        $this->available_quantity = $product->available_quantity;

        $this->is_active = $product->is_active;

        $this->sort_order = $product->sort_order;
    }



    public function render()
    {
        return view('livewire.admin.products.index', [
            'categories' => ProductCategory::with('group')
                ->orderBy('name')
                ->get(),

            'products' => Product::with([
                'category.group',
                'category.printer',
                'category.productionStation',
            ])
                ->when(
                    $this->search,
                    fn ($query) =>
                    $query->where(
                        'name',
                        'like',
                        '%' . $this->search . '%'
                    )
                )
                ->orderBy('sort_order')
                 ->orderBy('name')
                ->get(),


        ])->layout('components.layouts.app');
    }
}
