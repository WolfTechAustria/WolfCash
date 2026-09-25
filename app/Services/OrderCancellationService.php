<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use App\Models\OrderItemCancellation;
use App\Jobs\ProcessPrintOutput;
use App\Models\PrintJob;
use App\Models\PrintOutput;

class OrderCancellationService
{

    public function __construct(
        private readonly DailyClosingService $dailyClosingService,
        private readonly StockService $stockService,
    ) {
    }

    public function cancel(OrderItem $item, int $quantity, string $reason, ?int $userId = null): OrderItem
    {
        $this->dailyClosingService->assertOpen(today());

        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Die Stornomenge muss größer als 0 sein.'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'Ein Stornogrund ist erforderlich.'
            );
        }

        return DB::transaction(function () use (
            $item,
            $quantity,
            $reason,
            $userId
        ): OrderItem {
            $lockedItem = OrderItem::query()
                ->lockForUpdate()
                ->findOrFail($item->id);

            $openQuantity = max(
                0,
                $lockedItem->quantity
                - $lockedItem->cancelled_quantity
            );

            if ($quantity > $openQuantity) {
                throw new InvalidArgumentException(
                    "Es können höchstens {$openQuantity} Stück storniert werden."
                );
            }

            $newCancelledQuantity =
                $lockedItem->cancelled_quantity + $quantity;

            $lockedItem->update([
                'cancelled_quantity' => $newCancelledQuantity,
                'cancelled_at' => now(),
                'cancelled_by' => $userId,
                'cancellation_reason' => $reason,
            ]);

            /*
             * Stornierte Menge wieder in den Bestand zurückbuchen.
             */
            $this->stockService->restock(
                $lockedItem->product_id,
                $quantity
            );

            $cancellation = OrderItemCancellation::create([
                'order_item_id' => $lockedItem->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
            ]);

            $this->createCancellationOutputs(
                item: $lockedItem,
                cancellation: $cancellation,
            );

            $order = Order::query()
                ->lockForUpdate()
                ->findOrFail($lockedItem->order_id);

            $order->recalculateTotal();

            $this->closeOrderIfSettled($order);

            return $lockedItem->fresh([
                'product',
                'order',
            ]);
        });
    }

    private function createCancellationOutputs(
        OrderItem $item,
        OrderItemCancellation $cancellation
    ): void {
        $item->loadMissing([
            'product',
            'order.table',
            'cancelledByUser',
        ]);

        /*
         * Ein OrderItem kann in mehreren PrintJobs vorkommen.
         * Normalerweise ist es genau ein Produktionsjob.
         */
        $printJobs = PrintJob::query()
            ->where('order_id', $item->order_id)
            ->where('type', PrintJob::TYPE_PRODUCTION)
            ->whereNotNull('printer_id')
            ->whereJsonContains(
                'payload->items',
                [
                    'order_item_id' => $item->id,
                ]
            )
            ->get();

        /*
         * PostgreSQL unterstützt den obigen JSON-Vergleich bei Arrays
         * je nach Payload-Struktur nicht zuverlässig.
         * Deshalb zusätzlich in PHP filtern.
         */
        if ($printJobs->isEmpty()) {
            $printJobs = PrintJob::query()
                ->where('order_id', $item->order_id)
                ->where('type', PrintJob::TYPE_PRODUCTION)
                ->whereNotNull('printer_id')
                ->get()
                ->filter(function (PrintJob $job) use ($item): bool {
                    return collect(
                        $job->payload['items'] ?? []
                    )->contains(
                        fn (array $payloadItem): bool =>
                            (int) (
                                $payloadItem['order_item_id']
                                ?? 0
                            ) === $item->id
                    );
                });
        }

        foreach ($printJobs as $printJob) {
            /*
             * Ein Stornobon ist nur sinnvoll, wenn der ursprüngliche
             * Produktionsbon bereits freigegeben beziehungsweise
             * gedruckt wurde.
             *
             * Für noch nie ausgegebene Positionen muss die Küche nicht
             * über ein Storno informiert werden.
             */
            $originalWasReleased =
                $printJob->ready_to_print
                || $printJob->status
                === PrintJob::STATUS_PRINTED
                || $item->production_printed_quantity > 0;

            if (! $originalWasReleased) {
                continue;
            }

            $output = PrintOutput::create([
                'print_job_id' => $printJob->id,
                'order_item_id' => $item->id,
                'printer_id' => $printJob->printer_id,
                'quantity' => $cancellation->quantity,
                'type' => PrintOutput::TYPE_CANCELLATION,
                'status' => PrintOutput::STATUS_PENDING,
                'payload' => [
                    'table' => $item->order?->table?->number,
                    'name' => $item->product?->name
                        ?? 'Unbekanntes Produkt',
                    'quantity' => $cancellation->quantity,
                    'note' => $item->note,
                    'reason' => $cancellation->reason,
                    'cancelled_at' =>
                        $cancellation->cancelled_at?->toIso8601String(),
                    'cancelled_by' =>
                        $cancellation->cancelled_by,
                    'cancelled_by_name' =>
                        $cancellation->cancelledByUser?->name,
                ],
            ]);

            ProcessPrintOutput::dispatch($output->id)
                ->afterCommit();
        }
    }

    private function closeOrderIfSettled(Order $order): void
    {
        $openAmount = (float) $order->items()
            ->whereNull('paid_at')
            ->selectRaw(
                'COALESCE(SUM((quantity - cancelled_quantity) * price), 0) AS open_amount'
            )
            ->value('open_amount');

        if ($openAmount > 0.009) {
            return;
        }

        /*
         * Gab es bereits Zahlungen, wurde die Bestellung regulär
         * abgerechnet und der verbleibende Rest anschließend storniert.
         *
         * Ohne Zahlung handelt es sich um ein vollständiges Storno.
         */
        $status = $order->payments()->exists()
            ? Order::STATUS_PAID
            : Order::STATUS_CANCELLED;

        $order->update([
            'status' => $status,
        ]);

        $order->table()
            ->lockForUpdate()
            ->update([
                'status' => 'free',
            ]);
    }
}
