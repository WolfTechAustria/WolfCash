<?php

namespace App\Livewire\Admin\PrintJobs;

use App\Models\PrintJob;
use App\Printing\PrintJobPreview;
use Livewire\Component;
use Throwable;

class Index extends Component
{
    /**
     * IDs der aufgeklappten Zeilen. Nur für diese wird die
     * Bon-Vorschau gerendert.
     *
     * @var array<int, int>
     */
    public array $expanded = [];

    public function toggle(int $id): void
    {
        if (in_array($id, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$id]));

            return;
        }

        $this->expanded[] = $id;
    }

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

    public function render(PrintJobPreview $preview)
    {
        $jobs = PrintJob::with(['printer', 'order.table', 'productionStation'])
            ->latest()
            ->get();

        /*
         * Fehler beim Rendern sollen die Übersicht nicht sprengen –
         * in dem Fall wird die Meldung statt des Bons angezeigt.
         */
        $previews = [];

        foreach ($jobs->whereIn('id', $this->expanded) as $job) {
            try {
                $previews[$job->id] = $preview->render($job);
            } catch (Throwable $exception) {
                $previews[$job->id] = $exception->getMessage();
            }
        }

        return view('livewire.admin.print-jobs.index', [
            'jobs' => $jobs,
            'previews' => $previews,
        ])->layout('components.layouts.app');
    }
}
