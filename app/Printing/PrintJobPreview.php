<?php

namespace App\Printing;

use App\Models\PrintJob;
use App\Models\PrintOutput;

/**
 * Erzeugt für die Admin-Übersicht dieselben Dokumente, die auch
 * physisch am Drucker landen würden – ohne etwas zu speichern
 * oder zu drucken.
 */
class PrintJobPreview
{
    public function __construct(
        private readonly ProductionTicketRenderer $productionRenderer,
        private readonly PrintOutputRenderer $outputRenderer,
    ) {
    }

    /**
     * @return array<int, RenderedPrint>
     */
    public function render(PrintJob $job): array
    {
        if ($job->type !== PrintJob::TYPE_RECEIPT) {
            return $this->productionRenderer->render($job);
        }

        /*
         * Zahlungsbelege werden über PrintOutputs gedruckt. Für die
         * Vorschau reicht ein nicht gespeichertes Output mit dem
         * Snapshot aus dem PrintJob.
         */
        $output = new PrintOutput([
            'print_job_id' => $job->id,
            'order_item_id' => null,
            'printer_id' => $job->printer_id,
            'quantity' => 1,
            'type' => PrintOutput::TYPE_RECEIPT,
            'payload' => $job->payload ?? [],
        ]);

        $output->created_at = $job->created_at;
        $output->setRelation('printJob', $job);
        $output->setRelation('orderItem', null);

        return [
            $this->outputRenderer->render($output),
        ];
    }
}
