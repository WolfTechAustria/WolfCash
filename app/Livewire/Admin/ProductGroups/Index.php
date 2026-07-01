<?php

namespace App\Livewire\Admin\ProductGroups;

use Livewire\Component;
use App\Models\ProductGroup;

class Index extends Component
{
    public string $name = '';
    public int $sort_order = 0;
    public ?int $editingId = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|max:255',
            'sort_order' => 'required|integer|min:0',
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'sort_order' => $this->sort_order,
        ];

        if ($this->editingId) {
            ProductGroup::findOrFail($this->editingId)->update($data);
        } else {
            ProductGroup::create($data);
        }

        $this->reset(['name', 'editingId']);
        $this->sort_order = 0;
    }

    public function edit(int $id): void
    {
        $group = ProductGroup::findOrFail($id);

        $this->editingId = $group->id;
        $this->name = $group->name;
        $this->sort_order = $group->sort_order;
    }

    public function delete(int $id): void
    {
        $group = ProductGroup::withCount('categories')->findOrFail($id);

        if ($group->categories_count > 0) {
            $this->addError('delete', 'Diese Produktgruppe kann nicht gelöscht werden, solange Kategorien zugeordnet sind.');
            return;
        }

        $group->delete();
    }
    public function render()
    {
        return view('livewire.admin.product-groups.index', [
            'groups' => ProductGroup::orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
