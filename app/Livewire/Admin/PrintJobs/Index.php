<?php

namespace App\Livewire\Admin\PrintJobs;

use App\Models\PrintJob;
use Livewire\Component;

class Index extends Component
{
    public function markPrinted(int $id): void
    {
        $job = PrintJob::findOrFail($id);

        $job->update([
            'status' => PrintJob::STATUS_PRINTED,
            'printed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markFailed(int $id): void
    {
        $job = PrintJob::findOrFail($id);

        $job->update([
            'status' => PrintJob::STATUS_FAILED,
            'error_message' => 'Manuell als fehlgeschlagen markiert.',
        ]);
    }

    public function retry(int $id): void
    {
        $job = PrintJob::findOrFail($id);

        $job->update([
            'status' => PrintJob::STATUS_PENDING,
            'printed_at' => null,
            'error_message' => null,
        ]);
    }

    public function render()
    {
        return view('livewire.admin.print-jobs.index', [
            'jobs' => PrintJob::with(['printer', 'order.table'])
                ->latest()
                ->get(),
        ])->layout('components.layouts.app');
    }
}
