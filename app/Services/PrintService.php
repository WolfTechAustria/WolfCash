<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PrintJob;
use App\Models\Product;
use App\Jobs\ProcessPrintJob;
use App\Models\Printer;
use App\Models\Setting;

class PrintService
{
    public function createProductionJobs(Order $order, array $orderItems): void
    {
        $jobsByStation = [];

        /*
         * Alle Positionen eines Bonierungsvorgangs stammen vom selben
         * Gerät. Der Name wird als Snapshot mitgegeben, damit spätere
         * Umbenennungen alte Bons nicht verändern.
         */
        $origin = isset($orderItems[0])
            ? $orderItems[0]->origin_label
            : null;

        foreach ($orderItems as $orderItem) {
            $product = $orderItem->product()
                ->with([
                    'category.printer',
                    'category.productionStation',
                ])
                ->first();

            if (! $product) {
                continue;
            }

            if ($product->print_mode === Product::PRINT_NONE) {
                continue;
            }

            $category = $product->category;

            if (! $category) {
                continue;
            }

            $station = $category->productionStation;
            $printer = $category->printer;

            if (! $station) {
                continue;
            }

            $stationId = $station->id;

            if (! isset($jobsByStation[$stationId])) {
                $jobsByStation[$stationId] = [
                    'printer_id' => $printer?->id,
                    'print_trigger' => $printer?->print_trigger ?? Printer::PRINT_TRIGGER_IMMEDIATE,
                    'production_station_id' => $stationId,
                    'items' => [],
                ];
            }

            $jobsByStation[$stationId]['items'][] = [
                'order_item_id' => $orderItem->id,
                'name' => $product->name,
                'quantity' => $orderItem->quantity,
                'note' => $orderItem->note,
                'print_mode' => $product->print_mode,
            ];
        }

        foreach ($jobsByStation as $jobData) {
            if (empty($jobData['items'])) {
                continue;
            }

            /*
             * Ohne Drucker gibt es nichts zu drucken – der Bon existiert
             * dann nur am Küchenmonitor.
             */
            $hasPrinter = $jobData['printer_id'] !== null;

            $printImmediately = $hasPrinter
                && $jobData['print_trigger'] === Printer::PRINT_TRIGGER_IMMEDIATE;

            $printJob = PrintJob::create([
                'order_id' => $order->id,
                'printer_id' => $jobData['printer_id'],
                'production_station_id' => $jobData['production_station_id'],
                'type' => PrintJob::TYPE_PRODUCTION,
                'status' => PrintJob::STATUS_PENDING,
                'ready_to_print' => $printImmediately,
                /*
                 * Sofort gedruckte Bons liegen der Küche schon auf Papier
                 * vor, am Monitor würden sie nur doppelt auftauchen.
                 */
                'show_on_monitor' => ! $printImmediately,
                'payload' => [
                    'order_id' => $order->id,
                    'table' => $order->table?->number,
                    'origin' => $origin,
                    'items' => $jobData['items'],
                ],
            ]);

            /*
            * Nur sofort druckbare Jobs jetzt an die Queue senden.
            * Verzögerte Jobs werden später vom Küchenmonitor freigegeben.
            */
            if($printImmediately) {
                ProcessPrintJob::dispatch($printJob->id)
                    ->afterCommit();
            } else {
                ProductionBoard::changed($printJob->production_station_id);
            }
        }
    }

    /**
     * Erzeugt für eine stationäre Selbstbedienungskasse einen
     * Bestellbon auf dem fest konfigurierten Selbstbedienungs-Drucker,
     * statt Produktionsbons an die Stationsdrucker zu senden.
     *
     * @param array<int, \App\Models\OrderItem> $orderItems
     */
    public function createStationaryOrderJob(
        Order $order,
        array $orderItems,
        bool $printIndividually = false
    ): void {
        $items = [];

        foreach ($orderItems as $orderItem) {
            $product = $orderItem->product;

            $items[] = [
                'order_item_id' => $orderItem->id,
                'product_id' => $orderItem->product_id,
                'name' => $product?->name ?? 'Unbekanntes Produkt',
                'quantity' => $orderItem->quantity,
                'note' => $orderItem->note,
            ];
        }

        if ($items === []) {
            return;
        }

        $printerId = (int) ($order->table?->printer_id ?: Setting::stationaryPrinterId());

        $printJob = PrintJob::create([
            'order_id' => $order->id,
            'printer_id' => $printerId > 0 ? $printerId : null,
            'production_station_id' => null,
            'type' => PrintJob::TYPE_STATIONARY_ORDER,
            'status' => PrintJob::STATUS_PENDING,
            'ready_to_print' => true,
            'payload' => [
                'order_id' => $order->id,
                'table' => $order->table?->number,
                'items' => $items,
                'print_individually' => $printIndividually,
            ],
        ]);

        ProcessPrintJob::dispatch($printJob->id)
            ->afterCommit();
    }
}
