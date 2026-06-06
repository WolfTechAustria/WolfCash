<?php

namespace App\Livewire\Admin\ProductionStations;

use App\Models\ProductionStation;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';

    protected function rules(): array
    {
        return [
            'name' => 'required|max:255',
        ];
    }

    public function save(): void
    {
        $this->validate();

        ProductionStation::create([
            'name' => $this->name,
        ]);

        $this->reset('name');
    }

    public function delete(int $id): void
    {
        ProductionStation::findOrFail($id)
            ->delete();
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
