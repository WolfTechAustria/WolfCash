<?php

namespace App\Livewire\Admin\Printers;

use App\Models\Printer;
use Livewire\Component;

class Index extends Component
{
    public string $name = '';

    public string $ip_address = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|max:255',
            'ip_address' => 'nullable|max:255',
        ];
    }

    public function save(): void
    {
        $this->validate();

        Printer::create([
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'is_active' => $this->is_active,
        ]);

        $this->reset([
            'name',
            'ip_address',
        ]);

        $this->is_active = true;
    }

    public function toggle(int $id): void
    {
        $printer = Printer::findOrFail($id);

        $printer->update([
            'is_active' => !$printer->is_active,
        ]);
    }

    public function delete(int $id): void
    {
        Printer::findOrFail($id)->delete();
    }

    public function render()
    {
        return view(
            'livewire.admin.printers.index',
            [
                'printers' => Printer::orderBy('name')->get(),
            ]
        )->layout('components.layouts.app');
    }
}
