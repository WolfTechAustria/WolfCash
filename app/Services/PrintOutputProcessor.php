<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\PrintOutput;
use App\Printing\PrintOutputRenderer;
use App\Printing\PrintTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class PrintOutputProcessor
{
    public function __construct(
        private readonly PrintOutputRenderer $renderer,
        private readonly PrintTransport $transport,
    ) {
    }

    public function process(PrintOutput $output): void
    {
        if ($output->status === PrintOutput::STATUS_PRINTED) {
            return;
        }

        $output->loadMissing([
            'printer',
            'printJob',
            'orderItem',
        ]);

        if (! $output->printer) {
            $this->markAsFailed(
                $output,
                'Dem Ausdruck ist kein Drucker zugewiesen.'
            );

            return;
        }

        if (! $output->printJob) {
            $this->markAsFailed(
                $output,
                'Der zugehörige PrintJob wurde nicht gefunden.'
            );

            return;
        }

        $output->update([
            'status' => PrintOutput::STATUS_PRINTING,
            'error_message' => null,
        ]);

        try {
            $document = $this->renderer->render($output);

            $this->transport->print(
                $output->printer,
                $document
            );

            DB::transaction(function () use ($output): void {
                $output->update([
                    'status' => PrintOutput::STATUS_PRINTED,
                    'printed_at' => now(),
                    'error_message' => null,
                ]);

                /*
                 * Nur reguläre Produktionsausgaben erhöhen
                 * production_printed_quantity.
                 */
                if (
                    $output->type
                    !== PrintOutput::TYPE_PRODUCTION
                    || ! $output->order_item_id
                ) {
                    return;
                }

                $item = OrderItem::query()
                    ->lockForUpdate()
                    ->find($output->order_item_id);

                if (! $item) {
                    return;
                }

                $item->update([
                    'production_printed_quantity' => min(
                        $item->quantity,
                        $item->production_printed_quantity
                        + $output->quantity
                    ),
                ]);
            });

            Log::info(
                'Einzelausdruck erfolgreich verarbeitet.',
                [
                    'print_output_id' => $output->id,
                    'print_job_id' => $output->print_job_id,
                    'order_item_id' => $output->order_item_id,
                    'printer_id' => $output->printer_id,
                    'type' => $output->type,
                ]
            );
        } catch (Throwable $exception) {
            $this->markAsFailed(
                $output,
                $exception->getMessage()
            );

            Log::error(
                'Einzelausdruck fehlgeschlagen.',
                [
                    'print_output_id' => $output->id,
                    'print_job_id' => $output->print_job_id,
                    'order_item_id' => $output->order_item_id,
                    'printer_id' => $output->printer_id,
                    'type' => $output->type,
                    'exception' => $exception,
                ]
            );

            throw new RuntimeException(
                sprintf(
                    'PrintOutput #%d konnte nicht verarbeitet werden: %s',
                    $output->id,
                    $exception->getMessage()
                ),
                previous: $exception
            );
        }
    }

    private function markAsFailed(
        PrintOutput $output,
        string $message
    ): void {
        $output->update([
            'status' => PrintOutput::STATUS_FAILED,
            'error_message' => $message,
        ]);
    }
}
