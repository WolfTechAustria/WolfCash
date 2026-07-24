<?php

namespace App\Printing;

use App\Models\PrintOutput;
use RuntimeException;

class PrintOutputRenderer
{
    public function render(
        PrintOutput $output
    ): RenderedPrint {
        $output->loadMissing([
            'printJob.order.table',
            'printJob.productionStation',
            'orderItem.product',
        ]);

        $payload = $output->payload ?? [];

        $tableNumber =
            $output->printJob?->order?->table?->number
            ?? $payload['table']
            ?? '–';

        $name = $payload['name']
            ?? $output->orderItem?->product?->name
            ?? 'Unbekanntes Produkt';

        $quantity = max(
            1,
            (int) ($payload['quantity'] ?? $output->quantity)
        );

        $note = $payload['note']
            ?? $output->orderItem?->note;

        if (! $output->printJob) {
            throw new RuntimeException(
                "PrintOutput #{$output->id} besitzt keinen PrintJob."
            );
        }

        $lines = [
            'TISCH '.$tableNumber,
        ];

        if ($output->printJob->productionStation) {
            $lines[] = strtoupper(
                $output->printJob->productionStation->name
            );
        }

        $lines[] = $output->created_at
            ->format('d.m.Y H:i');

        $lines[] = str_repeat('-', 32);

        $lines[] = $quantity.'x '.$name;

        if ($note) {
            $lines[] = '  > '.$note;
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'Bon #'.$output->print_job_id;
        $lines[] = 'Ausgabe #'.$output->id;

        return new RenderedPrint(
            title: 'Produktionsbon',
            lines: $lines,
            cutPaper: true,
        );
    }
}
