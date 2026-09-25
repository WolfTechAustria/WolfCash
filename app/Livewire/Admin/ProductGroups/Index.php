<?php

namespace App\Livewire\Admin\ProductGroups;

use Livewire\Component;
use App\Models\ProductGroup;

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

        if ($this->editingId) {
            ProductGroup::findOrFail($this->editingId)->update([
                'name' => $this->name,
            ]);
        } else {
            ProductGroup::create([
                'name' => $this->name,
                'sort_order' => (int) ProductGroup::max('sort_order') + 1,
            ]);
        }

        $this->reset(['name', 'editingId']);
    }

    public function edit(int $id): void
    {
        $group = ProductGroup::findOrFail($id);

        $this->editingId = $group->id;
        $this->name = $group->name;
    }

    /**
     * @param array<int, int|string> $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        foreach ($orderedIds as $index => $id) {
            ProductGroup::whereKey($id)->update(['sort_order' => $index]);
        }
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
