<?php

namespace App\Livewire\Admin\Printers;

use App\Models\Printer;
use App\Services\PrinterTestService;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Throwable;

class Index extends Component
{
    public string $name = '';

    public string $ip_address = '';

    public int $port = 9100;

    public bool $is_active = true;

    public string $print_trigger = Printer::PRINT_TRIGGER_IMMEDIATE;

    public ?int $editingId = null;

    protected function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'ip_address' => [
                'nullable',
                'ip',
            ],

            'port' => [
                'required',
                'integer',
                'min:1',
                'max:65535',
            ],

            'is_active' => [
                'boolean',
            ],

            'print_trigger' => [
                'required',
                Rule::in([
                    Printer::PRINT_TRIGGER_IMMEDIATE,
                    Printer::PRINT_TRIGGER_ON_JOB_COMPLETE,
                ]),
            ],
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        /*
         * Ein leeres Eingabefeld sauber als NULL speichern.
         */
        $data['ip_address'] = $data['ip_address'] !== ''
            ? $data['ip_address']
            : null;

        if ($this->editingId !== null) {
            Printer::findOrFail($this->editingId)
                ->update($data);
        } else {
            Printer::create($data);
        }

        $this->resetForm();

        session()->flash(
            'success',
            'Drucker erfolgreich gespeichert.'
        );
    }

    public function edit(int $id): void
    {
        $printer = Printer::findOrFail($id);

        $this->editingId = $printer->id;
        $this->name = $printer->name;
        $this->ip_address = $printer->ip_address ?? '';
        $this->port = (int) ($printer->port ?: 9100);
        $this->is_active = (bool) $printer->is_active;

        $this->print_trigger = $printer->print_trigger
            ?? Printer::PRINT_TRIGGER_IMMEDIATE;

        $this->resetValidation();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
    }

    public function toggle(int $id): void
    {
        $printer = Printer::findOrFail($id);

        $printer->update([
            'is_active' => ! $printer->is_active,
        ]);
    }

    public function delete(int $id): void
    {
        $printer = Printer::query()
            ->withCount('categories')
            ->findOrFail($id);

        if ($printer->categories_count > 0) {
            $this->addError(
                'delete',
                'Dieser Drucker kann nicht gelöscht werden, solange Kategorien zugeordnet sind.'
            );

            return;
        }

        $printer->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash(
            'success',
            'Drucker erfolgreich gelöscht.'
        );
    }

    public function testPrinter(int $printerId): void
    {
        $printer = Printer::findOrFail($printerId);

        try {
            app(PrinterTestService::class)
                ->printTest($printer);

            session()->flash(
                'success',
                "Testdruck an '{$printer->name}' erfolgreich gesendet."
            );
        } catch (Throwable $exception) {
            report($exception);

            session()->flash(
                'error',
                $exception->getMessage()
            );
        }
    }

    private function resetForm(): void
    {
        $this->reset([
            'name',
            'ip_address',
            'editingId',
        ]);

        $this->port = 9100;
        $this->is_active = true;

        $this->print_trigger =
            Printer::PRINT_TRIGGER_IMMEDIATE;

        $this->resetValidation();
    }

    public function render()
    {
        return view(
            'livewire.admin.printers.index',
            [
                'printers' => Printer::query()
                    ->orderBy('name')
                    ->get(),
            ]
        )->layout('components.layouts.app');
    }
}
