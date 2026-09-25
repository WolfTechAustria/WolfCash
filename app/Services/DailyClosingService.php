<?php

namespace App\Services;

use App\Models\DailyClosing;
use App\Models\Order;
use App\Models\OrderItemCancellation;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DailyClosingService
{
    public function close(
        Carbon $businessDate,
        ?int $userId = null
    ): DailyClosing {
        $businessDate = $businessDate
            ->copy()
            ->startOfDay();

        if ($businessDate->isFuture()) {
            throw new RuntimeException(
                'Ein zukünftiger Geschäftstag kann nicht abgeschlossen werden.'
            );
        }

        return DB::transaction(function () use (
            $businessDate,
            $userId
        ): DailyClosing {
            /*
             * Parallele oder doppelte Abschlüsse verhindern.
             */
            $existingClosing = DailyClosing::query()
                ->whereDate('business_date', $businessDate)
                ->lockForUpdate()
                ->first();

            if ($existingClosing) {
                throw new RuntimeException(
                    'Dieser Geschäftstag wurde bereits abgeschlossen.'
                );
            }

            $orders = Order::query()
                ->with([
                    'items.product',
                    'payments',
                    'table',
                ])
                ->whereDate('created_at', $businessDate)
                ->lockForUpdate()
                ->get();

            /*
             * Offene Bestellungen verhindern einen Abschluss.
             */
            $openOrders = $orders->filter(
                fn (Order $order): bool =>
                    $order->status === Order::STATUS_OPEN
            );

            if ($openOrders->isNotEmpty()) {
                $orderNumbers = $openOrders
                    ->pluck('id')
                    ->implode(', ');

                throw new RuntimeException(
                    'Der Tagesabschluss ist nicht möglich. '
                    .'Folgende Bestellungen sind noch offen: '
                    .$orderNumbers
                );
            }

            $payments = Payment::query()
                ->with('order.table')
                ->whereDate('created_at', $businessDate)
                ->lockForUpdate()
                ->get();

            $cancellations = OrderItemCancellation::query()
                ->with([
                    'orderItem.product',
                    'orderItem.order.table',
                    'cancelledByUser',
                ])
                ->whereDate('cancelled_at', $businessDate)
                ->lockForUpdate()
                ->get();

            $grossAmount = $orders->sum(
                fn (Order $order): float =>
                $order->items->sum(
                    fn ($item): float =>
                        (float) $item->price
                        * (int) $item->quantity
                )
            );

            $payableAmount = $orders->sum(
                fn (Order $order): float =>
                $order->items->sum(
                    fn ($item): float =>
                        (float) $item->price
                        * max(
                            0,
                            (int) $item->quantity
                            - (int) $item->cancelled_quantity
                        )
                )
            );

            $cancelledAmount = $cancellations->sum(
                fn (OrderItemCancellation $cancellation): float =>
                    (float) (
                        $cancellation->orderItem?->price
                        ?? 0
                    )
                    * (int) $cancellation->quantity
            );

            /*
             * Eingelöste Bons sind bereits an der stationären Kassa als
             * Umsatz verbucht und dürfen hier nicht doppelt zählen.
             */
            $voucherOrderAmount = (float) $orders->sum(
                fn (Order $order): float => (float) $order->payments
                    ->where('payment_method', Payment::VOUCHER)
                    ->sum('amount')
            );

            $voucherAmount = (float) $payments
                ->where('payment_method', Payment::VOUCHER)
                ->sum('amount');

            $grossAmount -= $voucherOrderAmount;
            $payableAmount -= $voucherOrderAmount;

            $paidAmount = (float) $payments->sum('amount') - $voucherAmount;

            $cashAmount = (float) $payments
                ->where('payment_method', 'cash')
                ->sum('amount');

            $cardAmount = (float) $payments
                ->where('payment_method', 'card')
                ->sum('amount');

            $snapshot = [
                'version' => 1,

                'business_date' =>
                    $businessDate->format('Y-m-d'),

                'closed_at' => now()->toIso8601String(),

                'summary' => [
                    'orders_total' => $orders->count(),

                    'orders_paid' => $orders
                        ->where('status', Order::STATUS_PAID)
                        ->count(),

                    'orders_cancelled' => $orders
                        ->where(
                            'status',
                            Order::STATUS_CANCELLED
                        )
                        ->count(),

                    'gross_amount' => round(
                        $grossAmount,
                        2
                    ),

                    'cancelled_amount' => round(
                        $cancelledAmount,
                        2
                    ),

                    'payable_amount' => round(
                        $payableAmount,
                        2
                    ),

                    'paid_amount' => round(
                        $paidAmount,
                        2
                    ),

                    'cash_amount' => round(
                        $cashAmount,
                        2
                    ),

                    'card_amount' => round(
                        $cardAmount,
                        2
                    ),

                    'voucher_amount' => round(
                        $voucherAmount,
                        2
                    ),

                    'payments_count' => $payments->count(),

                    'cancellations_count' =>
                        $cancellations->count(),

                    'cancelled_quantity' =>
                        (int) $cancellations->sum('quantity'),
                ],

                /*
                 * Die Snapshot-Datensätze bleiben auch dann erhalten,
                 * wenn sich spätere Relationen oder Produktnamen ändern.
                 */
                'orders' => $orders
                    ->map(function (Order $order): array {
                        return [
                            'id' => $order->id,
                            'table_id' => $order->table_id,
                            'table_number' =>
                                $order->table?->number,
                            'status' => $order->status,
                            'total' => (float) $order->total,
                            'created_at' =>
                                $order->created_at?->toIso8601String(),

                            'items' => $order->items
                                ->map(fn ($item): array => [
                                    'id' => $item->id,
                                    'product_id' => $item->product_id,
                                    'product_name' =>
                                        $item->product?->name,
                                    'quantity' =>
                                        (int) $item->quantity,
                                    'cancelled_quantity' =>
                                        (int) $item->cancelled_quantity,
                                    'price' =>
                                        (float) $item->price,
                                    'note' => $item->note,
                                    'paid_at' =>
                                        $item->paid_at?->toIso8601String(),
                                ])
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all(),

                'payments' => $payments
                    ->map(fn (Payment $payment): array => [
                        'id' => $payment->id,
                        'order_id' => $payment->order_id,
                        'table_number' =>
                            $payment->order?->table?->number,
                        'payment_method' =>
                            $payment->payment_method,
                        'amount' =>
                            (float) $payment->amount,
                        'created_at' =>
                            $payment->created_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),

                'cancellations' => $cancellations
                    ->map(
                        function (
                            OrderItemCancellation $cancellation
                        ): array {
                            $item = $cancellation->orderItem;

                            return [
                                'id' => $cancellation->id,
                                'order_id' => $item?->order_id,
                                'order_item_id' =>
                                    $cancellation->order_item_id,
                                'table_number' =>
                                    $item?->order?->table?->number,
                                'product_name' =>
                                    $item?->product?->name,
                                'quantity' =>
                                    (int) $cancellation->quantity,
                                'price' =>
                                    (float) ($item?->price ?? 0),
                                'reason' =>
                                    $cancellation->reason,
                                'cancelled_by' =>
                                    $cancellation->cancelled_by,
                                'cancelled_at' =>
                                    $cancellation
                                        ->cancelled_at
                                        ?->toIso8601String(),
                            ];
                        }
                    )
                    ->values()
                    ->all(),
            ];

            return DailyClosing::create([
                'business_date' =>
                    $businessDate->format('Y-m-d'),

                'closed_at' => now(),
                'closed_by' => $userId,

                'orders_total' => $orders->count(),

                'orders_paid' => $orders
                    ->where('status', Order::STATUS_PAID)
                    ->count(),

                'orders_cancelled' => $orders
                    ->where(
                        'status',
                        Order::STATUS_CANCELLED
                    )
                    ->count(),

                'gross_amount' => round(
                    $grossAmount,
                    2
                ),

                'cancelled_amount' => round(
                    $cancelledAmount,
                    2
                ),

                'payable_amount' => round(
                    $payableAmount,
                    2
                ),

                'paid_amount' => round(
                    $paidAmount,
                    2
                ),

                'cash_amount' => round(
                    $cashAmount,
                    2
                ),

                'card_amount' => round(
                    $cardAmount,
                    2
                ),

                'payments_count' => $payments->count(),

                'cancellations_count' =>
                    $cancellations->count(),

                'cancelled_quantity' =>
                    (int) $cancellations->sum('quantity'),

                'snapshot' => $snapshot,
            ]);
        });
    }

    public function findForDate(
        Carbon $businessDate
    ): ?DailyClosing {
        return DailyClosing::query()
            ->with('closedByUser')
            ->whereDate(
                'business_date',
                $businessDate
            )
            ->first();
    }

    public function isClosed(
        Carbon $businessDate
    ): bool {
        return DailyClosing::query()
            ->whereDate(
                'business_date',
                $businessDate
            )
            ->exists();
    }

    public function assertOpen(
        Carbon $businessDate
    ): void {
        if ($this->isClosed($businessDate)) {
            throw new RuntimeException(
                'Der Geschäftstag '
                .$businessDate->format('d.m.Y')
                .' wurde bereits abgeschlossen.'
            );
        }
    }
}
