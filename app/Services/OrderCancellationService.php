<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use App\Models\OrderItemCancellation;

class OrderCancellationService
{
    public function cancel(
        OrderItem $item,
        int $quantity,
        string $reason,
        ?int $userId = null
    ): OrderItem {
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

            OrderItemCancellation::create([
                'order_item_id' => $lockedItem->id,
                'quantity' => $quantity,
                'reason' => $reason,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
            ]);

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
