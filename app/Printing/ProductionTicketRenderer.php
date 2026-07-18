<?php

namespace App\Printing;

use App\Models\PrintJob;

class ProductionTicketRenderer
{
    public function render(PrintJob $job): RenderedPrint
    {
        $job->loadMissing([
            'order.table',
            'productionStation',
        ]);

        $payload = $job->payload ?? [];
        $items = $payload['items'] ?? [];

        $tableNumber = $job->order?->table?->number ?? '–';

        $lines = [
            'TISCH '.$tableNumber,
        ];

        if ($job->productionStation) {
            $lines[] = strtoupper(
                $job->productionStation->name
            );
        }

        $lines[] = $job->created_at->format('d.m.Y H:i');
        $lines[] = str_repeat('-', 32);

        if ($items === []) {
            $lines[] = 'KEINE POSITIONEN';
        }

        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 1);
            $name = $item['name'] ?? 'Unbekanntes Produkt';
            $note = $item['note'] ?? null;
            $printMode = $item['print_mode'] ?? 'grouped';

            if ($printMode === 'split') {
                for ($unit = 1; $unit <= $quantity; $unit++) {
                    $lines[] = '1x '.$name;

                    if ($note) {
                        $lines[] = '  > '.$note;
                    }

                    if ($unit < $quantity) {
                        $lines[] = '';
                    }
                }

                continue;
            }

            $lines[] = $quantity.'x '.$name;

            if ($note) {
                $lines[] = '  > '.$note;
            }
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'Bon #'.$job->id;

        return new RenderedPrint(
            title: 'Produktionsbon',
            lines: $lines,
            cutPaper: true,
        );
    }
}
