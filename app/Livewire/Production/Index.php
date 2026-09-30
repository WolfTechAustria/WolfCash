<?php

namespace App\Livewire\Production;

use App\Jobs\ProcessPrintJob;
use App\Jobs\ProcessPrintOutput;
use App\Models\OrderItem;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintOutput;
use App\Models\Product;
use App\Models\ProductionStation;
use App\Services\ProductionBoard;
use App\Support\RowLock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Küchenmonitor.
 *
 * Mehrere Monitore arbeiten gleichzeitig auf denselben Bons. Tippt
 * jemand auf einen Bon, den ein anderer Monitor gerade abgeschlossen
 * hat, passiert nichts – der Monitor zeigt einfach den neuen Stand.
 *
 * Maßgeblich ist immer die offene (nicht stornierte) Menge einer
 * Position; vollständig stornierte Positionen gelten als erledigt.
 */
class Index extends Component
{
    #[Url]
    public ?int $station = null;

    public function selectStation(?int $stationId): void
    {
        $this->station = $stationId;
    }

    /**
     * Live-Signal über Reverb: neu rendern, sofern die Änderung
     * die gerade gewählte Station betrifft.
     *
     * @param  array{stationIds?: array<int, int>}  $payload
     */
    #[On('echo:production,.production.changed')]
    public function refreshBoard(array $payload = []): void
    {
        $stationIds = array_map('intval', $payload['stationIds'] ?? []);

        if (
            $this->station !== null
            && $stationIds !== []
            && ! in_array($this->station, $stationIds, true)
        ) {
            $this->skipRender();
        }
    }

    public function completeItemUnit(int $jobId, int $itemId): void
    {
        $this->changeItem($jobId, $itemId, fn (OrderItem $item): int => $item->production_completed_quantity + 1);
    }

    public function reopenItemUnit(int $jobId, int $itemId): void
    {
        $this->changeItem($jobId, $itemId, fn (OrderItem $item): int => $item->production_completed_quantity - 1);
    }

    public function completeGroupedItem(int $jobId, int $itemId): void
    {
        $this->changeItem($jobId, $itemId, fn (OrderItem $item): int => $item->open_quantity);
    }

    public function reopenGroupedItem(int $jobId, int $itemId): void
    {
        $this->changeItem($jobId, $itemId, fn (): int => 0);
    }

    public function completeJob(int $jobId): void
    {
        $job = DB::transaction(function () use ($jobId): ?PrintJob {
            $job = $this->lockOpenJob($jobId);

            if (! $job) {
                return null;
            }

            $items = RowLock::forUpdate(OrderItem::query())
                ->whereIn('id', $this->payloadItemIds($job))
                ->get();

            foreach ($items as $item) {
                $this->setCompletedQuantity($item, $item->open_quantity);
            }

            $job->update(['production_completed_at' => now()]);

            return $job;
        });

        if (! $job) {
            return;
        }

        $this->afterJobCompleted($job);

        foreach ($this->payloadItemIds($job) as $itemId) {
            $this->releaseItemOutputsIfRequired($job, $itemId);
        }

        ProductionBoard::changed($job->production_station_id);
    }

    /**
     * Setzt die fertige Menge einer Position und schließt den Bon ab,
     * sobald alle Positionen fertig sind – alles unter Zeilensperre,
     * damit zwei Monitore den Bon nicht doppelt abschließen.
     *
     * @param  \Closure(OrderItem): int  $completedQuantity
     */
    private function changeItem(int $jobId, int $itemId, \Closure $completedQuantity): void
    {
        $result = DB::transaction(function () use ($jobId, $itemId, $completedQuantity): ?array {
            $job = $this->lockOpenJob($jobId);

            if (! $job) {
                return null;
            }

            $itemIds = $this->payloadItemIds($job);

            if (! $itemIds->contains($itemId)) {
                return null;
            }

            $item = RowLock::forUpdate(OrderItem::query())->find($itemId);

            if (! $item) {
                return null;
            }

            $this->setCompletedQuantity($item, $completedQuantity($item));

            $jobCompleted = ! $this->hasOpenItems($itemIds);

            if ($jobCompleted) {
                $job->update(['production_completed_at' => now()]);
            }

            return [$job, $jobCompleted];
        });

        if (! $result) {
            return;
        }

        [$job, $jobCompleted] = $result;

        $this->releaseItemOutputsIfRequired($job, $itemId);

        if ($jobCompleted) {
            $this->afterJobCompleted($job);
        }

        ProductionBoard::changed($job->production_station_id);
    }

    private function lockOpenJob(int $jobId): ?PrintJob
    {
        return RowLock::forUpdate(PrintJob::query()->openOnMonitor())
            ->find($jobId);
    }

    /**
     * @return Collection<int, int>
     */
    private function payloadItemIds(PrintJob $job): Collection
    {
        return collect($job->payload['items'] ?? [])
            ->pluck('order_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values();
    }

    private function setCompletedQuantity(OrderItem $item, int $completed): void
    {
        $target = $item->open_quantity;
        $completed = max(0, min($completed, $target));

        $item->update([
            'production_completed_quantity' => $completed,

            'production_status' => match (true) {
                $completed >= $target => OrderItem::PRODUCTION_DONE,
                $completed > 0 => OrderItem::PRODUCTION_PROGRESS,
                default => OrderItem::PRODUCTION_PENDING,
            },
        ]);
    }

    /**
     * @param  Collection<int, int>  $itemIds
     */
    private function hasOpenItems(Collection $itemIds): bool
    {
        if ($itemIds->isEmpty()) {
            return true;
        }

        return OrderItem::query()
            ->whereIn('id', $itemIds)
            ->get()
            ->contains(
                fn (OrderItem $item): bool =>
                    $item->production_completed_quantity < $item->open_quantity
            );
    }

    private function afterJobCompleted(PrintJob $job): void
    {
        /*
         * Einzelausdrucke können schon gedruckt sein, bevor der Job
         * als fertig markiert ist – dann hier den Status nachziehen.
         */
        $job->syncStatusFromOutputs();

        $this->releasePrintJobIfRequired($job);
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
            function () use ($job, $payloadItem, $itemId): array {
                $item = RowLock::forUpdate(OrderItem::query())
                    ->findOrFail($itemId);

                $openQuantity = $item->open_quantity;

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
                    ->where('type', PrintOutput::TYPE_PRODUCTION)
                    ->count();

                $outputPayload = fn (int $quantity): array => [
                    'table' => $job->payload['table'] ?? null,
                    'origin' => $job->payload['origin'] ?? null,
                    'name' => $payloadItem['name']
                        ?? $item->product?->name
                        ?? 'Unbekanntes Produkt',
                    'quantity' => $quantity,
                    'note' => $payloadItem['note']
                        ?? $item->note,
                    'print_mode' => $printMode,
                ];

                /*
                 * Beim Einzelbon entspricht jede fertige Einheit
                 * genau einem physischen Bon.
                 */
                if ($printMode === Product::PRINT_SPLIT) {
                    $desiredOutputCount = min(
                        $item->production_completed_quantity,
                        $openQuantity
                    );

                    $outputsToCreate = max(
                        0,
                        $desiredOutputCount - $existingOutputCount
                    );

                    $outputIds = [];

                    for ($number = 0; $number < $outputsToCreate; $number++) {
                        $outputIds[] = PrintOutput::create([
                            'print_job_id' => $job->id,
                            'order_item_id' => $item->id,
                            'printer_id' => $job->printer_id,
                            'quantity' => 1,
                            'type' => PrintOutput::TYPE_PRODUCTION,
                            'status' => PrintOutput::STATUS_PENDING,
                            'payload' => $outputPayload(1),
                        ])->id;
                    }

                    return $outputIds;
                }

                /*
                 * Gruppenposition:
                 * Erst wenn die gesamte offene Menge fertig ist,
                 * genau einen Bon mit dieser Menge erzeugen.
                 */
                if (
                    $openQuantity === 0
                    || $item->production_completed_quantity < $openQuantity
                    || $existingOutputCount > 0
                ) {
                    return [];
                }

                return [
                    PrintOutput::create([
                        'print_job_id' => $job->id,
                        'order_item_id' => $item->id,
                        'printer_id' => $job->printer_id,
                        'quantity' => $openQuantity,
                        'type' => PrintOutput::TYPE_PRODUCTION,
                        'status' => PrintOutput::STATUS_PENDING,
                        'payload' => $outputPayload($openQuantity),
                    ])->id,
                ];
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
            ->openOnMonitor()
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
         * Eine einzige Abfrage statt OrderItem::find() in der View.
         */
        $orderItems = OrderItem::query()
            ->with('product')
            ->whereIn(
                'id',
                $jobs->flatMap(fn (PrintJob $job) => $this->payloadItemIds($job))->unique()
            )
            ->get()
            ->keyBy('id');

        /*
         * Für jeden PrintJob nur dessen eigene Payload-Positionen
         * zusammenstellen.
         */
        $jobCards = $jobs->map(function (PrintJob $job) use ($orderItems) {
            $items = collect($job->payload['items'] ?? [])
                ->map(function (array $payloadItem) use ($orderItems) {
                    $orderItem = $orderItems->get((int) ($payloadItem['order_item_id'] ?? 0));

                    if (! $orderItem) {
                        return null;
                    }

                    $openQuantity = $orderItem->open_quantity;
                    $completed = min(
                        (int) $orderItem->production_completed_quantity,
                        $openQuantity
                    );

                    return [
                        'id' => $orderItem->id,

                        'name' => $payloadItem['name']
                            ?? $orderItem->product?->name
                            ?? 'Unbekanntes Produkt',

                        'quantity' => $openQuantity,

                        'cancelled_quantity' => min(
                            (int) $orderItem->cancelled_quantity,
                            (int) $orderItem->quantity
                        ),

                        'note' => $payloadItem['note']
                            ?? $orderItem->note,

                        'print_mode' => $payloadItem['print_mode']
                            ?? $orderItem->product?->print_mode
                            ?? Product::PRINT_GROUPED,

                        'completed_quantity' => $completed,

                        'done' => $openQuantity > 0 && $completed >= $openQuantity,
                    ];
                })
                ->filter()
                ->values();

            return [
                'job' => $job,
                'items' => $items,
                'has_open_items' => $items->contains(
                    fn (array $item): bool => $item['quantity'] > 0 && ! $item['done']
                ),
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
