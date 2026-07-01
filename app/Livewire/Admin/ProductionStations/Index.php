<?php

namespace App\Livewire\Admin\ProductionStations;

use App\Models\ProductionStation;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';
    public ?int $editingId = null;
    protected function rules(): array
    {
        return [
            'name' => 'required|max:255',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
        ];

        if ($this->editingId) {
            ProductionStation::findOrFail($this->editingId)->update($data);
        } else {
            ProductionStation::create($data);
        }

        $this->reset([
            'name',
            'editingId',
        ]);
    }

    public function edit(int $id): void
    {
        $station = ProductionStation::findOrFail($id);

        $this->editingId = $station->id;
        $this->name = $station->name;
    }

    public function delete(int $id): void
    {
        $station = ProductionStation::withCount('categories')->findOrFail($id);

        if ($station->categories_count > 0) {
            $this->addError('delete', 'Diese Produktionsstation kann nicht gelöscht werden, solange Kategorien zugeordnet sind.');
            return;
        }

        $station->delete();
    }

    public function render()
    {
        return view(
            'livewire.admin.production-stations.index',
            [
                'stations' => ProductionStation::orderBy('name')
                    ->get(),
            ]
        )->layout('components.layouts.app');
    }
}
