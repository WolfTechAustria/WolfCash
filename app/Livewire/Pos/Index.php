<?php

namespace App\Livewire\Pos;

use Livewire\Component;
use App\Models\Table;

class Index extends Component
{
    public ?int $selectedTable = null;

    public function selectTable(int $tableId): void
    {
        $this->selectedTable = $tableId;
    }

    public function backToTables(): void
    {
        $this->selectedTable = null;
    }

    public function render()
    {
        return view('livewire.pos.index', [
            'tables' => Table::orderBy('number')->get(),
            'table' => $this->selectedTable
                ? Table::find($this->selectedTable)
                : null,
        ])->layout('components.layouts.app');
    }
}
