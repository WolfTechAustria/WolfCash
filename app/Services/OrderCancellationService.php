<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Table;
use App\Support\RowLock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use App\Models\OrderItemCancellation;
use App\Jobs\ProcessPrintOutput;
use App\Models\PrintJob;
use App\Models\PrintOutput;

class OrderCancellationService
{

    public function __construct(
        private readonly DailyClosingService $dailyClosingService,
        private readonly StockService $stockService,
        private readonly ActivityLogger $activityLogger,
    ) {
    }

    /**
     * @param bool $printCancellationTicket false für nachträgliche
     *        Stornos im Admin, bei denen die Küche nichts mehr tun muss.
     */
    public function cancel(
        OrderItem $item,
        int $quantity,
        string $reason,
        ?int $userId = null,
        bool $printCancellationTicket = true,
    ): OrderItem {
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
            $userId,
            $printCancellationTicket
        ): OrderItem {
            Table::lockForBooking(
                (int) Order::query()->whereKey($item->order_id)->value('table_id')
            );

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

            /*
             * Bereits bezahlte Position: Zahlung und gespeicherter
             * Beleg werden um das Storno reduziert, damit Kassastand
             * und Belegnachdruck stimmen. Ist der Zahlungstag schon
             * abgeschlossen, wird abgelehnt — der gespeicherte
             * Tagesabschluss würde sonst nicht mehr stimmen.
             */
            $paymentId = $this->paymentIdFor($lockedItem);

            $payment = $paymentId
                ? RowLock::forUpdate(Payment::query())
                    ->find($paymentId)
                : null;

            if ($payment) {
                if ($this->dailyClosingService->isClosed($payment->created_at)) {
                    throw new RuntimeException(
                        'Die Position wurde am '
                        .$payment->created_at->format('d.m.Y')
                        .' bezahlt und dieser Tag ist bereits abgeschlossen. '
                        .'Ein Storno ist nicht mehr möglich.'
                    );
                }

                $this->reducePayment(
                    payment: $payment,
                    item: $lockedItem,
                    quantity: $quantity,
                    reason: $reason,
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
             * Stornierte Menge wieder in den Bestand zurückbuchen –
             * außer bei per Bon bezahlten Positionen, die bereits beim
             * Einlösen zurückgebucht wurden.
             */
            if ($payment?->payment_method !== Payment::VOUCHER) {
                $this->stockService->restock(
                    $lockedItem->product_id,
                    $quantity
                );
            }

            $cancellation = OrderItemCancellation::create([
                'order_item_id' => $lockedItem->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
            ]);

            if ($printCancellationTicket) {
                $this->createCancellationOutputs(
                    item: $lockedItem,
                    cancellation: $cancellation,
                );
            }

            /*
             * Offene Bons am Küchenmonitor zeigen die stornierte
             * Menge sofort an.
             */
            ProductionBoard::changed(
                $lockedItem->product?->category?->production_station_id
            );

            $order = RowLock::forUpdate(Order::query())
                ->findOrFail($lockedItem->order_id);

            $order->recalculateTotal();

            $order->closeIfSettled();

            $this->activityLogger->log(
                ActivityLog::ITEM_CANCELLED,
                sprintf(
                    'Tisch %s: %d× %s storniert (%s)',
                    $order->table?->number ?? '–',
                    $quantity,
                    $lockedItem->product?->name ?? 'Unbekanntes Produkt',
                    $reason
                ),
                $cancellation,
                [
                    'order_id' => $order->id,
                    'order_item_id' => $lockedItem->id,
                    'quantity' => $quantity,
                    'amount' => round((float) $lockedItem->price * $quantity, 2),
                    'reason' => $reason,
                    'payment_id' => $payment?->id,
                ],
                $userId,
            );

            return $lockedItem->fresh([
                'product',
                'order',
            ]);
        });
    }

    /**
     * Positionen, die vor Einführung von order_items.payment_id bezahlt
     * wurden, haben nur paid_at. Dann über den Beleg-Snapshot zuordnen,
     * der diese Position enthält, sonst über die einzige Zahlung.
     */
    private function paymentIdFor(OrderItem $item): ?int
    {
        if ($item->payment_id) {
            return (int) $item->payment_id;
        }

        if ($item->paid_at === null) {
            return null;
        }

        $receiptJobs = PrintJob::query()
            ->where('order_id', $item->order_id)
            ->where('type', PrintJob::TYPE_RECEIPT)
            ->whereNotNull('payment_id')
            ->get();

        foreach ($receiptJobs as $job) {
            $containsItem = collect($job->payload['items'] ?? [])->contains(
                fn (array $line): bool =>
                    (int) ($line['order_item_id'] ?? 0) === $item->id
            );

            if ($containsItem) {
                return (int) $job->payment_id;
            }
        }

        $paymentIds = Payment::query()
            ->where('order_id', $item->order_id)
            ->pluck('id');

        return $paymentIds->count() === 1
            ? (int) $paymentIds->first()
            : null;
    }

    /**
     * Zieht den stornierten Betrag von der Zahlung ab und korrigiert
     * den gespeicherten Beleg-Snapshot, damit ein Nachdruck den
     * tatsächlich bezahlten Betrag samt Storno-Hinweis zeigt.
     */
    private function reducePayment(
        Payment $payment,
        OrderItem $item,
        int $quantity,
        string $reason
    ): void {
        $refund = round((float) $item->price * $quantity, 2);

        $payment->update([
            'amount' => max(0, round((float) $payment->amount - $refund, 2)),
        ]);

        $receiptJob = PrintJob::query()
            ->where('payment_id', $payment->id)
            ->where('type', PrintJob::TYPE_RECEIPT)
            ->lockForUpdate()
            ->first();

        if (! $receiptJob) {
            return;
        }

        $payload = $receiptJob->payload ?? [];
        $items = array_values($payload['items'] ?? []);

        $lineIndex = $this->receiptLineIndex($items, $item);

        if ($lineIndex !== null) {
            $line = $items[$lineIndex];
            $remaining = max(0, (int) ($line['quantity'] ?? 0) - $quantity);

            if ($remaining === 0) {
                array_splice($items, $lineIndex, 1);
            } else {
                $items[$lineIndex]['quantity'] = $remaining;
                $items[$lineIndex]['total'] = round(
                    (float) ($line['unit_price'] ?? $item->price) * $remaining,
                    2
                );
            }
        }

        $payload['items'] = $items;
        $payload['amount'] = round((float) $payment->amount, 2);
        $payload['corrected_at'] = now()->toIso8601String();
        $payload['cancellations'] = [
            ...($payload['cancellations'] ?? []),
            [
                'order_item_id' => $item->id,
                'name' => $item->product?->name ?? 'Unbekanntes Produkt',
                'quantity' => $quantity,
                'unit_price' => round((float) $item->price, 2),
                'total' => $refund,
                'reason' => $reason,
                'cancelled_at' => now()->toIso8601String(),
            ],
        ];

        $receiptJob->update([
            'payload' => $payload,
        ]);
    }

    /**
     * Bei Teilzahlungen verweist der Snapshot auf die ursprüngliche
     * (unbezahlte) Position statt auf die abgespaltene bezahlte —
     * deshalb ersatzweise über Produkt und Preis zuordnen.
     *
     * @param array<int, array<string, mixed>> $items
     */
    private function receiptLineIndex(array $items, OrderItem $item): ?int
    {
        foreach ($items as $index => $line) {
            if ((int) ($line['order_item_id'] ?? 0) === $item->id) {
                return $index;
            }
        }

        foreach ($items as $index => $line) {
            if (
                (int) ($line['product_id'] ?? 0) === (int) $item->product_id
                && abs((float) ($line['unit_price'] ?? 0) - (float) $item->price) < 0.005
            ) {
                return $index;
            }
        }

        return null;
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
}
