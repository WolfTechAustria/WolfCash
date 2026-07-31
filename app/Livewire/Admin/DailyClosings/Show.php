<?php

namespace App\Livewire\Admin\DailyClosings;

use App\Models\DailyClosing;
use Livewire\Component;

class Show extends Component
{
    public DailyClosing $dailyClosing;

    public array $snapshot = [];

    public function mount(
        DailyClosing $dailyClosing
    ): void {
        $this->dailyClosing = $dailyClosing->load(
            'closedByUser'
        );

        $this->snapshot =
            $this->dailyClosing->snapshot ?? [];
    }

    public function render()
    {
        return view(
            'livewire.admin.daily-closings.show'
        )->layout('components.layouts.app');
    }
}
