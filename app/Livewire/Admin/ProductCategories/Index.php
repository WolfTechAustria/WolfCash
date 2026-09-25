<?php

namespace App\Livewire\Admin\ProductCategories;

use App\Models\ProductCategory;
use App\Models\ProductGroup;
use Livewire\Component;
use App\Models\Printer;
use App\Models\ProductionStation;

class Index extends Component
{
    public string $name = '';

    public ?int $product_group_id = null;
    public ?int $printer_id = null;
    public ?int $production_station_id = null;

    public ?int $editingId = null;

    public ?int $filterGroupId = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|max:255',

            'product_group_id'
            => 'required|exists:product_groups,id',

            'printer_id'
            => 'nullable|exists:printers,id',

            'production_station_id'
            => 'nullable|exists:production_stations,id',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'product_group_id' => $this->product_group_id,
            'printer_id' => $this->printer_id,
            'production_station_id' => $this->production_station_id,
        ];

        if ($this->editingId) {
            ProductCategory::findOrFail($this->editingId)->update($data);
        } else {
            $data['sort_order'] = (int) ProductCategory::where('product_group_id', $this->product_group_id)
                ->max('sort_order') + 1;

            ProductCategory::create($data);
        }

        $this->reset([
            'name',
            'product_group_id',
            'printer_id',
            'production_station_id',
            'editingId',
        ]);
    }

    public function edit(int $id): void
    {
        $category = ProductCategory::findOrFail($id);

        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->product_group_id = $category->product_group_id;
        $this->printer_id = $category->printer_id;
        $this->production_station_id = $category->production_station_id;
    }

    /**
     * @param array<int, int|string> $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            ProductCategory::whereKey($id)->update(['sort_order' => $index]);
        }
    }

    public function delete(int $id): void
    {
        $category = ProductCategory::withCount('products')->findOrFail($id);

        if ($category->products_count > 0) {
            $this->addError('delete', 'Diese Kategorie kann nicht gelöscht werden, solange Produkte zugeordnet sind.');
            return;
        }

        $category->delete();
    }

    public function render()
    {
        return view(
            'livewire.admin.product-categories.index',
            [
                'categories' => ProductCategory::with('group')
                    ->when(
                        $this->filterGroupId,
                        fn ($query) => $query->where('product_group_id', $this->filterGroupId)
                    )
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(),

                'groups' => ProductGroup::orderBy('name')
                    ->get(),

                'printers' => Printer::orderBy('name')
                    ->get(),

                'stations' => ProductionStation::orderBy('name')
                    ->get(),
            ]
        )->layout('components.layouts.app');
    }
}
