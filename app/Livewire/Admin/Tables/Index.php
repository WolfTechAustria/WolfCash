<?php

namespace App\Livewire\Admin\Tables;

use Livewire\Component;
use App\Models\Table;
use App\Services\TableOrderSessionService;

class Index extends Component
{
    public string $number = '';
    public string $name = '';

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

    public function save()
    {
        $this->validate();

        Table::create([
            'number' => $this->number,
            'name' => $this->name,
            'status' => 'free',
            'self_order_enabled' => true,
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
            'tables' => Table::query()
                ->with('activeTableOrderSession')
                ->orderBy('number')
                ->get(),
        ])->layout('components.layouts.app');
    }
}
