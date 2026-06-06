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

        ProductCategory::create([
            'name' => $this->name,

            'product_group_id'
            => $this->product_group_id,

            'printer_id'
            => $this->printer_id,

            'production_station_id'
            => $this->production_station_id,
        ]);

        $this->reset([
            'name',
            'product_group_id',
            'printer_id',
            'production_station_id',
        ]);
    }

    public function delete(int $id): void
    {
        ProductCategory::findOrFail($id)->delete();
    }

    public function render()
    {
        return view(
            'livewire.admin.product-categories.index',
            [
                'categories' => ProductCategory::with('group')
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
