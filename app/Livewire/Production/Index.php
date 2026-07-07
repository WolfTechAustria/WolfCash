<?php

namespace App\Livewire\Production;

use App\Models\PrintJob;
use Livewire\Component;

class Index extends Component
{
    public function complete(int $id): void
    {
        $job = PrintJob::findOrFail($id);

        $job->update([
            'status' => PrintJob::STATUS_PRINTED,
            'printed_at' => now(),
        ]);
    }

    public function render()
    {
        return view('livewire.production.index', [

            'jobs' => PrintJob::with([
                'printer',
                'order.table',
            ])
                ->where(
                    'status',
                    PrintJob::STATUS_PENDING
                )
                ->oldest()
                ->get(),

        ])->layout('components.layouts.app');
    }
}
