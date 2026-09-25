<?php

namespace App\Livewire\Admin\Tables;

use Livewire\Component;
use App\Models\Printer;
use App\Models\Setting;
use App\Models\Table;
use App\Services\TableOrderSessionService;

class Index extends Component
{
    public string $number = '';
    public string $name = '';
    public bool $isStationary = false;

    public ?int $qrTableId = null;

    public ?string $qrTableNumber = null;

    public ?string $qrUrl = null;

    public bool $qrModalOpen = false;

    protected function rules(): array
    {
        return [
            'number' => 'required|max:20',
            'name' => 'nullable|max:255',
        ];
    }

    public function showQr(
        int $tableId,
        TableOrderSessionService $sessionService
    ): void {
        $table = Table::findOrFail(
            $tableId
        );

        $result =
            $sessionService->getOrCreate(
                $table
            );

        $this->qrTableId =
            $table->id;

        $this->qrTableNumber =
            (string) $table->number;

        $this->qrUrl =
            $result['url'];

        $this->qrModalOpen =
            true;
    }

    public function closeQr(): void
    {
        $this->qrModalOpen =
            false;

        $this->qrTableId =
            null;

        $this->qrTableNumber =
            null;

        $this->qrUrl =
            null;
    }

    public function regenerateQr(
        int $tableId,
        TableOrderSessionService $sessionService
    ): void {
        $table = Table::findOrFail(
            $tableId
        );

        $result =
            $sessionService->regenerate(
                $table
            );

        $this->qrTableId =
            $table->id;

        $this->qrTableNumber =
            (string) $table->number;

        $this->qrUrl =
            $result['url'];

        $this->qrModalOpen =
            true;
    }

    public function toggleSelfOrder(
        int $tableId
    ): void {
        $table = Table::findOrFail(
            $tableId
        );

        $table->update([
            'self_order_enabled' =>
                ! $table->self_order_enabled,
        ]);
    }

    public function toggleStationary(
        int $tableId
    ): void {
        $table = Table::findOrFail(
            $tableId
        );

        $table->update([
            'is_stationary' =>
                ! $table->is_stationary,
        ]);
    }

    public function setPrinter(int $tableId, ?string $printerId): void
    {
        $printerId = $printerId === null || $printerId === ''
            ? null
            : Printer::query()->findOrFail((int) $printerId)->id;

        Table::query()
            ->where('is_stationary', true)
            ->findOrFail($tableId)
            ->update(['printer_id' => $printerId]);
    }

    public function save()
    {
        $this->validate();

        Table::create([
            'number' => $this->number,
            'name' => $this->name,
            'status' => 'free',
            'self_order_enabled' => true,
            'is_stationary' => $this->isStationary,
        ]);

        $this->reset([
            'number',
            'name',
            'isStationary',
        ]);
    }

    public function delete(int $id)
    {
        Table::findOrFail($id)->delete();
    }

    public function render()
    {
        return view('livewire.admin.tables.index', [
            'tables' => Table::query()
                ->with('activeTableOrderSession')
                ->orderBy('number')
                ->get(),

            'printers' => Printer::query()
                ->orderBy('name')
                ->get(),

            'defaultStationaryPrinter' => Printer::find(Setting::stationaryPrinterId()),
        ])->layout('components.layouts.app');
    }
}
