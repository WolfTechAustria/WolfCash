<?php

namespace App\Livewire\Admin\ProductGroups;

use Livewire\Component;
use App\Models\ProductGroup;

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

        ProductGroup::create([
            'name' => $this->name,
        ]);

        $this->reset('name');
    }

    public function delete(int $id): void
    {
        ProductGroup::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.product-groups.index', [
            'groups' => ProductGroup::orderBy('name')->get(),
        ])->layout('components.layouts.app');
    }
}
