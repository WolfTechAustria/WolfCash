<?php

namespace App\Jobs;

use App\Models\PrintJob;
use App\Services\PrintJobProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class ProcessPrintJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 20;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $printJobId,
    ) {
        $this->onQueue('printing');
    }

    /**
     * Wartezeiten zwischen den Versuchen.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [
            5,
            15,
            30,
            60,
        ];
    }

    public function handle(
        PrintJobProcessor $processor
    ): void {
        $printJob = PrintJob::find($this->printJobId);

        if (! $printJob) {
            throw new RuntimeException(
                "PrintJob #{$this->printJobId} wurde nicht gefunden."
            );
        }

        /*
         * Verhindert einen erneuten Ausdruck, falls der Queue-Job
         * nach einem erfolgreichen Druck nochmals ausgeführt wird.
         */
        if ($printJob->status === PrintJob::STATUS_PRINTED) {
            return;
        }

        /*
         * Noch nicht freigegebene Produktionsbons dürfen
         * nicht gedruckt werden.
         */

        if (! $printJob->ready_to_print) {
            return;
        }

        $processor->process($printJob);

        $printJob->refresh();

        /*
         * Falls der Processor Fehler intern abfängt und lediglich den
         * Status setzt, müssen wir trotzdem eine Exception auslösen,
         * damit Laravel den Queue-Job erneut versucht.
         */
        if ($printJob->status !== PrintJob::STATUS_PRINTED) {
            throw new RuntimeException(
                $printJob->error_message
                    ?: "PrintJob #{$printJob->id} konnte nicht gedruckt werden."
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $printJob = PrintJob::find($this->printJobId);

        if (! $printJob) {
            return;
        }

        $printJob->update([
            'status' => PrintJob::STATUS_FAILED,
            'error_message' => $exception?->getMessage()
                ?? 'Der Druckauftrag ist endgültig fehlgeschlagen.',
        ]);
    }
}
