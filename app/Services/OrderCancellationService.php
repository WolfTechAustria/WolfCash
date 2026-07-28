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

            return $lockedItem->fresh([
                'product',
                'order',
            ]);
        });
    }
}
