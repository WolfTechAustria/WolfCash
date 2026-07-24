<?php

namespace App\Services;

use App\Models\PrintJob;
use App\Printing\PrintTransport;
use App\Printing\ProductionTicketRenderer;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PrintJobProcessor
{
    public function __construct(
        private readonly ProductionTicketRenderer $renderer,
        private readonly PrintTransport $transport,
    ) {
    }

    public function process(PrintJob $job): void
    {
        if ($job->status === PrintJob::STATUS_PRINTED) {
            return;
        }

        if (! $job->printer_id) {
            $this->markAsFailed(
                $job,
                'Dem Druckauftrag ist kein Drucker zugewiesen.'
            );

            return;
        }

        $job->loadMissing([
            'printer',
            'order.table',
            'order.items.product',
            'productionStation',
        ]);

        if (! $job->printer) {
            $this->markAsFailed(
                $job,
                "Der zugewiesene Drucker #{$job->printer_id} wurde nicht gefunden."
            );

            return;
        }

        if (! $job->order) {
            $this->markAsFailed(
                $job,
                'Die zum Druckauftrag gehörende Bestellung wurde nicht gefunden.'
            );

            return;
        }

        $job->update([
            'status' => PrintJob::STATUS_PRINTING,
            'error_message' => null,
        ]);

        try {
            /*
             * Ein PrintJob kann jetzt mehrere physische
             * Dokumente beziehungsweise Papierbons erzeugen.
             */
            $documents = $this->renderer->render($job);

            if ($documents === []) {
                throw new RuntimeException(
                    'Der Renderer hat keine Druckdokumente erzeugt.'
                );
            }

            foreach ($documents as $document) {
                $this->transport->print(
                    $job->printer,
                    $document
                );
            }

            $job->update([
                'status' => PrintJob::STATUS_PRINTED,
                'printed_at' => now(),
                'error_message' => null,
            ]);

            Log::info(
                'Druckauftrag erfolgreich verarbeitet.',
                [
                    'print_job_id' => $job->id,
                    'printer_id' => $job->printer_id,
                    'order_id' => $job->order_id,
                    'document_count' => count($documents),
                ]
            );
        } catch (Throwable $exception) {
            $this->markAsFailed(
                $job,
                $exception->getMessage()
            );

            Log::error(
                'Druckauftrag fehlgeschlagen.',
                [
                    'print_job_id' => $job->id,
                    'printer_id' => $job->printer_id,
                    'order_id' => $job->order_id,
                    'exception' => $exception,
                ]
            );

            throw new RuntimeException(
                sprintf(
                    'PrintJob #%d konnte nicht verarbeitet werden: %s',
                    $job->id,
                    $exception->getMessage()
                ),
                previous: $exception
            );
        }
    }

    private function markAsFailed(
        PrintJob $job,
        string $message
    ): void {
        $job->update([
            'status' => PrintJob::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }
}
