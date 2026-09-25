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

        if ($job->type === PrintJob::TYPE_STATIONARY_ORDER) {
            return $this->renderStationaryOrderTickets($job);
        }

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

    /**
     * Erzeugt für eine stationäre Selbstbedienungskasse
     * einen eigenen Bon pro unterschiedlichem Artikel,
     * mit dem sich Kund:innen anstellen können.
     *
     * @return array<int, RenderedPrint>
     */
    private function renderStationaryOrderTickets(
        PrintJob $job
    ): array {
        $payload = $job->payload ?? [];
        $items = $payload['items'] ?? [];

        if ($items === []) {
            return [
                $this->renderEmptyJob($job),
            ];
        }

        if (! empty($payload['print_individually'])) {
            return $this->renderStationaryIndividualTickets($job, $items);
        }

        $groupedByArticle = [];

        foreach ($items as $item) {
            $key = $item['product_id']
                ?? $item['name']
                ?? 'unbekannt';

            if (! isset($groupedByArticle[$key])) {
                $groupedByArticle[$key] = [
                    'name' => $item['name']
                        ?? 'Unbekanntes Produkt',
                    'quantity' => 0,
                    'notes' => [],
                ];
            }

            $groupedByArticle[$key]['quantity'] += max(
                1,
                (int) ($item['quantity'] ?? 1)
            );

            if (! empty($item['note'])) {
                $groupedByArticle[$key]['notes'][] =
                    $item['note'];
            }
        }

        return array_map(
            fn (array $article): RenderedPrint =>
                $this->renderStationaryArticleTicket(
                    $job,
                    $article
                ),
            array_values($groupedByArticle)
        );
    }

    /**
     * @param array{name: string, quantity: int, notes: array<int, string>} $article
     */
    private function renderStationaryArticleTicket(
        PrintJob $job,
        array $article
    ): RenderedPrint {
        $lines = [
            'SELBSTBEDIENUNG',
            'Bestellnummer #'.($job->order_id ?? '–'),
            $job->created_at->format('d.m.Y H:i'),
            str_repeat('-', 32),

            $article['quantity'].'x '.$article['name'],
        ];

        foreach ($article['notes'] as $note) {
            $lines[] = '  > '.$note;
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'Bon #'.$job->id;

        return new RenderedPrint(
            title: 'Bestellbon',
            lines: $lines,
            cutPaper: true,
        );
    }

    /**
     * Erzeugt für jede einzelne Einheit einen eigenen Bon
     * (z. B. 20x Schnitzel -> 20 Bons á 1 Schnitzel), statt
     * pro Artikel zu bündeln.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, RenderedPrint>
     */
    private function renderStationaryIndividualTickets(
        PrintJob $job,
        array $items
    ): array {
        $documents = [];

        foreach ($items as $item) {
            $quantity = max(
                1,
                (int) ($item['quantity'] ?? 1)
            );

            for ($unit = 1; $unit <= $quantity; $unit++) {
                $documents[] = $this->renderStationaryUnitTicket(
                    $job,
                    $item,
                    $unit,
                    $quantity
                );
            }
        }

        return $documents;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function renderStationaryUnitTicket(
        PrintJob $job,
        array $item,
        int $unit,
        int $totalQuantity
    ): RenderedPrint {
        $name = $item['name'] ?? 'Unbekanntes Produkt';
        $note = $item['note'] ?? null;

        $lines = [
            'SELBSTBEDIENUNG',
            'Bestellnummer #'.($job->order_id ?? '–'),
            $job->created_at->format('d.m.Y H:i'),
            str_repeat('-', 32),

            '1x '.$name,
        ];

        if ($note) {
            $lines[] = '  > '.$note;
        }

        if ($totalQuantity > 1) {
            $lines[] = '';
            $lines[] = sprintf(
                'Einzelbon %d/%d',
                $unit,
                $totalQuantity
            );
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'Bon #'.$job->id;

        return new RenderedPrint(
            title: 'Bestellbon',
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
