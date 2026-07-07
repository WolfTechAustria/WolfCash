<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;

class PaymentService
{
    public function paySelection(
        Order $order,
        array $selectedForPayment,
        string $method
    ): void {
        $amount = 0;

        foreach ($selectedForPayment as $itemId => $quantity) {
            $item = OrderItem::where('order_id', $order->id)
                ->whereNull('paid_at')
                ->find($itemId);

            if (! $item) {
                continue;
            }

            $payQuantity = min((int) $quantity, $item->quantity);

            if ($payQuantity <= 0) {
                continue;
            }

            $amount += $item->price * $payQuantity;

            if ($payQuantity === $item->quantity) {
                $item->update([
                    'paid_at' => now(),
                ]);
            } else {
                OrderItem::create([
                    'order_id' => $item->order_id,
                    'product_id' => $item->product_id,
                    'quantity' => $payQuantity,
                    'price' => $item->price,
                    'note' => $item->note,
                    'status' => $item->status,
                    'paid_at' => now(),
                ]);

                $item->update([
                    'quantity' => $item->quantity - $payQuantity,
                ]);
            }
        }

        if ($amount > 0) {
            Payment::create([
                'order_id' => $order->id,
                'amount' => $amount,
                'payment_method' => $method,
            ]);
        }
    }

    public function payRemaining(
        Order $order,
        string $method
    ): void {
        $openItems = $order->items()
            ->whereNull('paid_at')
            ->get();

        $amount = $openItems->sum(
            fn ($item) => $item->price * $item->quantity
        );

        if ($amount <= 0) {
            return;
        }

        $order->items()
            ->whereNull('paid_at')
            ->update([
                'paid_at' => now(),
            ]);

        Payment::create([
            'order_id' => $order->id,
            'amount' => $amount,
            'payment_method' => $method,
        ]);
    }
}
