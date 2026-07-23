<?php

namespace App\Livewire\Admin\Printers;

use App\Models\Printer;
use Livewire\Component;
use App\Services\PrinterTestService;

class Index extends Component
{
    public string $name = '';
    public string $ip_address = '';
    public bool $is_active = true;
    public ?int $editingId = null;

    protected function rules(): array
    {
        return [
            'name' => 'required|max:255',
            'ip_address' => 'nullable|max:255',
            'is_enabled' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'ip_address' => $this->ip_address,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            Printer::findOrFail($this->editingId)->update($data);
        } else {
            Printer::create($data);
        }

        $this->reset([
            'name',
            'ip_address',
            'editingId',
        ]);

        $this->is_active = true;
    }

    public function edit(int $id): void
    {
        $printer = Printer::findOrFail($id);

        $this->editingId = $printer->id;
        $this->name = $printer->name;
        $this->ip_address = $printer->ip_address ?? '';
        $this->is_active = $printer->is_active;
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
        $printer = Printer::withCount('categories')->findOrFail($id);

        if ($printer->categories_count > 0) {
            $this->addError('delete', 'Dieser Drucker kann nicht gelöscht werden, solange Kategorien zugeordnet sind.');
            return;
        }

        $printer->delete();
    }

    public function testPrinter(int $printerId): void
    {
        $printer = Printer::findOrFail($printerId);

        try {
            app(PrinterTestService::class)
                ->printTest($printer);

            session()->flash(
                'success',
                'Testdruck erfolgreich gesendet.'
            );

        } catch (\Throwable $e) {

            report($e);

            session()->flash(
                'error',
                $e->getMessage()
            );
        }
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
