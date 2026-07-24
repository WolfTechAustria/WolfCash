<?php

namespace App\Livewire\Production;

use App\Models\OrderItem;
use App\Models\PrintJob;
use App\Models\ProductionStation;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessPrintJob;
use App\Models\Printer;
use App\Jobs\ProcessPrintOutput;
use App\Models\PrintOutput;
use App\Models\Product;

class Index extends Component
{
    #[Url]
    public ?int $station = null;

    public function selectStation(?int $stationId): void
    {
        $this->station = $stationId;
    }

    public function completeItemUnit( int $jobId, int $itemId): void
    {
        $job = PrintJob::query()
            ->whereNull('production_completed_at')
            ->findOrFail($jobId);

        $payloadItemIds = collect($job->payload['items'] ?? [])
            ->pluck('order_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        abort_unless(
            $payloadItemIds->contains($itemId),
            404
        );

        DB::transaction(function () use ($itemId): void {
            $item = OrderItem::query()
                ->lockForUpdate()
                ->findOrFail($itemId);

            $completedQuantity = min(
                $item->production_completed_quantity + 1,
                $item->quantity
            );

            $item->update([
                'production_completed_quantity' => $completedQuantity,

                'production_status' => match (true) {
                    $completedQuantity >= $item->quantity =>
                    OrderItem::PRODUCTION_DONE,

                    $completedQuantity > 0 =>
                    OrderItem::PRODUCTION_PROGRESS,

                    default =>
                    OrderItem::PRODUCTION_PENDING,
                },
            ]);
        });

        $this->releaseItemOutputsIfRequired(
            $job,
            $itemId
        );

        $this->finishJobWhenAllItemsAreDone(
            $job,
            $payloadItemIds
        );
    }

    public function reopenItemUnit( int $jobId, int $itemId): void
    {
        $job = PrintJob::query()
            ->whereNull('production_completed_at')
            ->findOrFail($jobId);

        $payloadItemIds = collect($job->payload['items'] ?? [])
            ->pluck('order_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        abort_unless(
            $payloadItemIds->contains($itemId),
            404
        );

        DB::transaction(function () use ($itemId): void {
            $item = OrderItem::query()
                ->lockForUpdate()
                ->findOrFail($itemId);

            $completedQuantity = max(
                $item->production_completed_quantity - 1,
                0
            );

            $item->update([
                'production_completed_quantity' => $completedQuantity,

                'production_status' => match (true) {
                    $completedQuantity >= $item->quantity =>
                    OrderItem::PRODUCTION_DONE,

                    $completedQuantity > 0 =>
                    OrderItem::PRODUCTION_PROGRESS,

                    default =>
                    OrderItem::PRODUCTION_PENDING,
                },
            ]);
        });
    }

    public function completeGroupedItem(int $jobId, int $itemId): void {
        $job = PrintJob::query()
            ->whereNull('production_completed_at')
            ->findOrFail($jobId);

        $payloadItemIds = collect($job->payload['items'] ?? [])
            ->pluck('order_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        abort_unless($payloadItemIds->contains($itemId), 404);

        $item = OrderItem::findOrFail($itemId);

        $item->update([
            'production_completed_quantity' => $item->quantity,
            'production_status' => OrderItem::PRODUCTION_DONE,
        ]);

        $this->releaseItemOutputsIfRequired(
            $job,
            $itemId
        );

        $this->finishJobWhenAllItemsAreDone(
            $job,
            $payloadItemIds
        );
    }

    public function reopenGroupedItem(int $jobId, int $itemId): void {
        $job = PrintJob::query()
            ->whereNull('production_completed_at')
            ->findOrFail($jobId);

        $payloadItemIds = collect($job->payload['items'] ?? [])
            ->pluck('order_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        abort_unless(
            $payloadItemIds->contains($itemId),
            404
        );

        OrderItem::query()
            ->whereKey($itemId)
            ->update([
                'production_completed_quantity' => 0,
                'production_status' => OrderItem::PRODUCTION_PENDING,
            ]);
    }

    public function completeJob(int $jobId): void
    {
        $job = PrintJob::query()
            ->whereNull('production_completed_at')
            ->findOrFail($jobId);

        $itemIds = collect($job->payload['items'] ?? [])
            ->pluck('order_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id);

        DB::transaction(function () use ($job, $itemIds): void {
            if ($itemIds->isNotEmpty()) {
                $items = OrderItem::query()
                    ->whereIn('id', $itemIds)
                    ->lockForUpdate()
                    ->get();

                foreach ($items as $item) {
                    $item->update([
                        'production_completed_quantity' => $item->quantity,
                        'production_status' => OrderItem::PRODUCTION_DONE,
                    ]);
                }
            }

            $job->update([
                'production_completed_at' => now(),
            ]);
        });

        foreach ($itemIds as $itemId) {
            $this->releaseItemOutputsIfRequired(
                $job,
                (int) $itemId
            );
        }

        $this->releasePrintJobIfRequired($job);
    }

    private function finishJobWhenAllItemsAreDone(PrintJob $job, Collection $itemIds): void {
        if ($itemIds->isEmpty()) {
            return;
        }

        $openItemsExist = OrderItem::query()
            ->whereIn('id', $itemIds)
            ->where(
                'production_status',
                '!=',
                OrderItem::PRODUCTION_DONE
            )
            ->exists();

        if (! $openItemsExist) {
            $job->update([
                'production_completed_at' => now(),
            ]);

            $this->releasePrintJobIfRequired($job);
        }
    }

    private function releasePrintJobIfRequired(PrintJob $job): void
    {
        $job->refresh();
        $job->loadMissing('printer');

        if (! $job->printer) {
            return;
        }

        if (
            $job->printer->print_trigger
            !== Printer::PRINT_TRIGGER_ON_JOB_COMPLETE
        ) {
            return;
        }

        if ($job->status === PrintJob::STATUS_PRINTED) {
            return;
        }

        if ($job->ready_to_print) {
            return;
        }

        $job->update([
            'ready_to_print' => true,
        ]);

        ProcessPrintJob::dispatch($job->id);
    }

    private function releaseItemOutputsIfRequired(
        PrintJob $job,
        int $itemId
    ): void {
        $job->loadMissing('printer');

        if (! $job->printer) {
            return;
        }

        if (
            $job->printer->print_trigger
            !== Printer::PRINT_TRIGGER_ON_ITEM_COMPLETE
        ) {
            return;
        }

        $payloadItem = collect($job->payload['items'] ?? [])
            ->first(
                fn (array $item) =>
                    (int) ($item['order_item_id'] ?? 0) === $itemId
            );

        if (! $payloadItem) {
            return;
        }

        $outputIds = DB::transaction(
            function () use ($job, $itemId, $payloadItem): array {
                $item = OrderItem::query()
                    ->lockForUpdate()
                    ->findOrFail($itemId);

                $printMode = $payloadItem['print_mode']
                    ?? Product::PRINT_GROUPED;

                /*
                 * Bereits erzeugte PrintOutputs zählen.
                 *
                 * Auch pending/failed zählen mit, da fehlgeschlagene
                 * Ausgaben über denselben Queue-Job wiederholt werden.
                 */
                $existingOutputCount = PrintOutput::query()
                    ->where('print_job_id', $job->id)
                    ->where('order_item_id', $item->id)
                    ->count();

                /*
                 * Beim Einzelbon entspricht jede fertige Einheit
                 * genau einem physischen Bon.
                 */
                if ($printMode === Product::PRINT_SPLIT) {
                    $desiredOutputCount = min(
                        $item->production_completed_quantity,
                        $item->quantity
                    );

                    $outputsToCreate = max(
                        0,
                        $desiredOutputCount - $existingOutputCount
                    );

                    $outputIds = [];

                    for ($number = 0; $number < $outputsToCreate; $number++) {
                        $output = PrintOutput::create([
                            'print_job_id' => $job->id,
                            'order_item_id' => $item->id,
                            'printer_id' => $job->printer_id,
                            'quantity' => 1,
                            'type' => PrintOutput::TYPE_PRODUCTION,
                            'status' => PrintOutput::STATUS_PENDING,
                            'payload' => [
                                'table' => $job->payload['table'] ?? null,
                                'name' => $payloadItem['name']
                                    ?? $item->product?->name
                                        ?? 'Unbekanntes Produkt',
                                'quantity' => 1,
                                'note' => $payloadItem['note']
                                    ?? $item->note,
                                'print_mode' => $printMode,
                            ],
                        ]);

                        $outputIds[] = $output->id;
                    }

                    return $outputIds;
                }

                /*
                 * Gruppenposition:
                 * Erst wenn die gesamte Position fertig ist,
                 * genau einen Bon mit der Gesamtmenge erzeugen.
                 */
                if (
                    $item->production_completed_quantity < $item->quantity
                    || $existingOutputCount > 0
                ) {
                    return [];
                }

                $output = PrintOutput::create([
                    'print_job_id' => $job->id,
                    'order_item_id' => $item->id,
                    'printer_id' => $job->printer_id,
                    'quantity' => $item->quantity,
                    'type' => PrintOutput::TYPE_PRODUCTION,
                    'status' => PrintOutput::STATUS_PENDING,
                    'payload' => [
                        'table' => $job->payload['table'] ?? null,
                        'name' => $payloadItem['name']
                            ?? $item->product?->name
                                ?? 'Unbekanntes Produkt',
                        'quantity' => $item->quantity,
                        'note' => $payloadItem['note']
                            ?? $item->note,
                        'print_mode' => $printMode,
                    ],
                ]);

                return [$output->id];
            }
        );

        foreach ($outputIds as $outputId) {
            ProcessPrintOutput::dispatch($outputId);
        }
    }

    public function render()
    {
        $jobs = PrintJob::query()
            ->with([
                'order.table',
                'productionStation',
            ])
            ->whereNull('production_completed_at')
            ->when(
                $this->station !== null,
                fn ($query) => $query->where(
                    'production_station_id',
                    $this->station
                )
            )
            ->oldest()
            ->get();

        /*
         * Alle OrderItem-IDs aus allen angezeigten Bons sammeln.
         */
        $orderItemIds = $jobs
            ->flatMap(
                fn (PrintJob $job) =>
                collect($job->payload['items'] ?? [])
                    ->pluck('order_item_id')
            )
            ->filter()
            ->unique()
            ->values();

        /*
         * Eine einzige Abfrage statt OrderItem::find() in der View.
         */
        $orderItems = OrderItem::query()
            ->with('product')
            ->whereIn('id', $orderItemIds)
            ->get()
            ->keyBy('id');

        /*
         * Für jeden PrintJob nur dessen eigene Payload-Positionen
         * zusammenstellen.
         */
        $jobCards = $jobs->map(function (PrintJob $job) use ($orderItems) {
            $items = collect($job->payload['items'] ?? [])
                ->map(function (array $payloadItem) use ($orderItems) {
                    $orderItemId = $payloadItem['order_item_id'] ?? null;

                    if (! $orderItemId) {
                        return null;
                    }

                    $orderItem = $orderItems->get((int) $orderItemId);

                    if (! $orderItem) {
                        return null;
                    }

                    return [
                        'id' => $orderItem->id,

                        'name' => $payloadItem['name']
                            ?? $orderItem->product?->name
                            ?? 'Unbekanntes Produkt',

                        'quantity' => (int) (
                            $payloadItem['quantity']
                            ?? $orderItem->quantity
                        ),

                        'note' => $payloadItem['note']
                            ?? $orderItem->note,

                        'print_mode' => $payloadItem['print_mode']
                            ?? $orderItem->product?->print_mode
                            ?? \App\Models\Product::PRINT_GROUPED,

                        'production_status' =>
                            $orderItem->production_status,

                        'production_completed_quantity' =>
                            (int) $orderItem->production_completed_quantity,
                    ];
                })
                ->filter()
                ->values();

            return [
                'job' => $job,
                'items' => $items,
            ];
        });

        return view('livewire.production.index', [
            'stations' => ProductionStation::query()
                ->orderBy('name')
                ->get(),

            'jobCards' => $jobCards,
        ])->layout('components.layouts.app');
    }
}
