<?php

namespace App\Printing;

use App\Models\PrintJob;

class ProductionTicketRenderer
{
    /**
     * @return array<int, RenderedPrint>
     */
    public function render(PrintJob $job): array
    {
        $job->loadMissing([
            'order.table',
            'productionStation',
        ]);

        $payload = $job->payload ?? [];
        $items = $payload['items'] ?? [];

        $groupedItems = [];
        $documents = [];

        foreach ($items as $item) {
            $quantity = max(
                1,
                (int) ($item['quantity'] ?? 1)
            );

            $printMode = $item['print_mode'] ?? 'grouped';

            /*
             * Einzelbon:
             * Für jede einzelne Einheit ein eigenes Dokument erzeugen.
             */
            if ($printMode === 'split') {
                for ($unit = 1; $unit <= $quantity; $unit++) {
                    $documents[] = $this->renderSingleItem(
                        $job,
                        $item,
                        $unit,
                        $quantity
                    );
                }

                continue;
            }

            /*
             * Gruppenpositionen werden später gemeinsam
             * auf einem Bon ausgegeben.
             */
            $groupedItems[] = $item;
        }

        if ($groupedItems !== []) {
            array_unshift(
                $documents,
                $this->renderGroupedItems(
                    $job,
                    $groupedItems
                )
            );
        }

        /*
         * Sollte der Payload leer sein, trotzdem ein Diagnose-Dokument
         * erzeugen, damit der Job nachvollziehbar bleibt.
         */
        if ($documents === []) {
            $documents[] = $this->renderEmptyJob($job);
        }

        return $documents;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function renderGroupedItems(
        PrintJob $job,
        array $items
    ): RenderedPrint {
        $lines = $this->createHeaderLines($job);

        foreach ($items as $item) {
            $quantity = max(
                1,
                (int) ($item['quantity'] ?? 1)
            );

            $name = $item['name']
                ?? 'Unbekanntes Produkt';

            $note = $item['note'] ?? null;

            $lines[] = $quantity.'x '.$name;

            if ($note) {
                $lines[] = '  > '.$note;
            }
        }

        $this->appendFooterLines($lines, $job);

        return new RenderedPrint(
            title: 'Produktionsbon',
            lines: $lines,
            cutPaper: true,
        );
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderSingleItem(
        PrintJob $job,
        array $item,
        int $unit,
        int $totalQuantity
    ): RenderedPrint {
        $lines = $this->createHeaderLines($job);

        $name = $item['name']
            ?? 'Unbekanntes Produkt';

        $note = $item['note'] ?? null;

        $lines[] = '1x '.$name;

        if ($note) {
            $lines[] = '  > '.$note;
        }

        /*
         * Nur zur eindeutigen Zuordnung bei mehreren gleichen Bons.
         */
        if ($totalQuantity > 1) {
            $lines[] = '';
            $lines[] = sprintf(
                'Einzelbon %d/%d',
                $unit,
                $totalQuantity
            );
        }

        $this->appendFooterLines($lines, $job);

        return new RenderedPrint(
            title: 'Produktionsbon',
            lines: $lines,
            cutPaper: true,
        );
    }

    private function renderEmptyJob(
        PrintJob $job
    ): RenderedPrint {
        $lines = $this->createHeaderLines($job);

        $lines[] = 'KEINE POSITIONEN';

        $this->appendFooterLines($lines, $job);

        return new RenderedPrint(
            title: 'Produktionsbon',
            lines: $lines,
            cutPaper: true,
        );
    }

    /**
     * @return array<int, string>
     */
    private function createHeaderLines(
        PrintJob $job
    ): array {
        $tableNumber =
            $job->order?->table?->number ?? '–';

        $lines = [
            'TISCH '.$tableNumber,
        ];

        if ($job->productionStation) {
            $lines[] = strtoupper(
                $job->productionStation->name
            );
        }

        $lines[] = $job->created_at
            ->format('d.m.Y H:i');

        $lines[] = str_repeat('-', 32);

        return $lines;
    }

    /**
     * @param array<int, string> $lines
     */
    private function appendFooterLines(
        array &$lines,
        PrintJob $job
    ): void {
        $lines[] = str_repeat('-', 32);
        $lines[] = 'Bon #'.$job->id;
    }
}
