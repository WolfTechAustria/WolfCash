<?php

namespace App\Livewire\Admin\Tables;

use Livewire\Component;
use App\Models\Table;

class Index extends Component
{
    public string $number = '';
    public string $name = '';

    protected function rules(): array
    {
        return [
            'number' => 'required|max:20',
            'name' => 'nullable|max:255',
        ];
    }

    public function save()
    {
        $this->validate();

        Table::create([
            'number' => $this->number,
            'name' => $this->name,
            'status' => 'free',
        ]);

        $this->reset([
            'number',
            'name',
        ]);
    }

    public function delete(int $id)
    {
        Table::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.tables.index', [
            'tables' => Table::orderBy('number')->get(),
        ])->layout('components.layouts.app');
    }
}
