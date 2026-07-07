<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;

class OrderService
{
    public function createOrder(int $tableId,array $cart, ?PrintService $printService = null): Order
    {
        $table = Table::findOrFail($tableId);

        $order = Order::where('table_id',$table->id)
            ->where('status',Order::STATUS_OPEN)
            ->first();

        if (!$order) {
            $order = Order::create([
                'table_id' => $table->id,
                'status' => Order::STATUS_OPEN,
                'total' => 0,
            ]);
        }

        foreach ($cart as $item) {

            $existingItem = OrderItem::where('order_id',$order->id)
                ->where('product_id',$item['id'])
                ->first();

            if ($existingItem) {
                $existingItem->increment(
                    'quantity',
                    $item['quantity']
                );

            } else {

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'status' => OrderItem::STATUS_PENDING,
                    'note' => $item['note'] ?? null,
                ]);
            }
        }

        $order->total = $order->items()
            ->sum(
                DB::raw('quantity * price')
            );

        $order->save();

        $table->update([
            'status' => 'occupied',
        ]);

        if ($printService) {
            $printService->createProductionJobs($order, $cart);
        }

        return $order;
    }
}
