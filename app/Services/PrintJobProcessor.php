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
        /*
         * Verhindert, dass ein bereits erfolgreich gedruckter Job
         * versehentlich nochmals ausgegeben wird.
         */
        if ($job->status === PrintJob::STATUS_PRINTED) {
            return;
        }

        /*
         * Der Druckjob muss einem Drucker zugewiesen sein.
         */
        if (! $job->printer_id) {
            $this->markAsFailed(
                $job,
                'Dem Druckauftrag ist kein Drucker zugewiesen.'
            );

            return;
        }

        /*
         * Alle für Renderer und Transport benötigten Relationen laden.
         *
         * Bereits geladene Relationen werden dabei nicht erneut abgefragt.
         */
        $job->loadMissing([
            'printer',
            'order.table',
            'order.items.product',
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

        /*
         * Der Job befindet sich ab jetzt in Verarbeitung.
         */
        $job->update([
            'status' => PrintJob::STATUS_PRINTING,
            'error_message' => null,
        ]);

        try {
            /*
             * Der Renderer erzeugt das druckerunabhängige Dokument.
             */
            $document = $this->renderer->render($job);

            /*
             * Der konfigurierte Transport übernimmt die physische
             * oder simulierte Ausgabe.
             */
            $this->transport->print(
                $job->printer,
                $document
            );

            /*
             * Nur der Druckstatus wird hier abgeschlossen.
             *
             * production_completed_at wird ausschließlich durch
             * den Produktionsmonitor gesetzt, wenn Küche oder Bar
             * die Positionen tatsächlich fertiggestellt haben.
             */
            $job->update([
                'status' => PrintJob::STATUS_PRINTED,
                'printed_at' => now(),
                'error_message' => null,
            ]);

            Log::info('Druckauftrag erfolgreich verarbeitet.', [
                'print_job_id' => $job->id,
                'printer_id' => $job->printer_id,
                'order_id' => $job->order_id,
            ]);
        } catch (Throwable $exception) {
            $this->markAsFailed(
                $job,
                $exception->getMessage()
            );

            Log::error('Druckauftrag fehlgeschlagen.', [
                'print_job_id' => $job->id,
                'printer_id' => $job->printer_id,
                'order_id' => $job->order_id,
                'exception' => $exception,
            ]);

            /*
             * Wichtig für die Laravel Queue:
             *
             * Die Exception wird erneut geworfen, damit der Queue-Job
             * als fehlgeschlagen gilt und gemäß Retry-Konfiguration
             * erneut versucht wird.
             */
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
