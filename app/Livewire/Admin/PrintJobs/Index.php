<?php

namespace App\Livewire\Admin\PrintJobs;

use App\Jobs\ProcessPrintJob;
use App\Jobs\ProcessPrintOutput;
use App\Models\PrintJob;
use App\Models\PrintOutput;
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

        /*
         * Belege und Bons mit Auslöser "pro Position" laufen über
         * Einzelausdrucke: fehlgeschlagene erneut einreihen.
         */
        $failedOutputs = $job->outputs()
            ->where('type', '!=', PrintOutput::TYPE_CANCELLATION)
            ->where('status', PrintOutput::STATUS_FAILED)
            ->get();

        if ($failedOutputs->isNotEmpty()) {
            foreach ($failedOutputs as $output) {
                $output->update([
                    'status' => PrintOutput::STATUS_PENDING,
                    'error_message' => null,
                ]);

                ProcessPrintOutput::dispatch($output->id);
            }

            return;
        }

        if ($job->outputs()->where('type', '!=', PrintOutput::TYPE_CANCELLATION)->exists()) {
            $job->syncStatusFromOutputs();

            return;
        }

        /*
         * Noch nicht freigegebene Produktionsbons warten weiter auf
         * den Küchenmonitor, alles andere direkt neu drucken.
         */
        if ($job->ready_to_print) {
            ProcessPrintJob::dispatch($job->id);
        }
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
