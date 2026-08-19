<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessPrintJob;

class OrderService
{
    public function __construct(private readonly DailyClosingService $dailyClosingService) {

    }
    public function createOrder(int $tableId, array $cart, ?PrintService $printService = null, bool $forceNewOrder = false):Order
    {
        $this->dailyClosingService->assertOpen(today());

        return DB::transaction(function () use ($tableId, $cart, $printService)
        {
            $table = Table::findOrFail($tableId);

            $order = null;

            /*
             * Normale Kellnerbestellungen werden weiterhin
             * an die offene Tischbestellung angehängt.
             *
             * SelfOrders können dagegen bewusst eine
             * eigenständige Order erzwingen.
             */
            if (! $forceNewOrder) {
                $order = Order::query()
                    ->where('table_id', $table->id)
                    ->where('status', Order::STATUS_OPEN)
                    ->first();
            }

            if (! $order) {
                $order = Order::create([
                    'table_id' => $table->id,
                    'status' => Order::STATUS_OPEN,
                    'total' => 0,
                ]);
            }

            $createdItems = [];

            foreach ($cart as $item) {
                $quantity = (int) ($item['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                /*
                 * Immer eine neue Bestellposition erstellen.
                 *
                 * Auch wenn dasselbe Produkt bereits auf der
                 * Bestellung vorhanden ist.
                 */
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['id'],
                    'quantity' => $quantity,
                    'price' => $item['price'],
                    'note' => $item['note'] ?? null,
                    'paid_at' => $item['paid_at'] ?? null,
                    'status' => OrderItem::STATUS_PENDING,
                    'production_status' => OrderItem::PRODUCTION_PENDING,
                    'production_completed_quantity' => 0,
                    'production_printed_quantity' => 0,
                ]);

                $createdItems[] = $orderItem;
            }

            $order->recalculateTotal();

            $table->update([
                'status' => 'occupied',
            ]);

            /*
             * Nur die gerade neu bonierten Positionen
             * an den PrintService übergeben.
             */
            if ($printService && $createdItems !== []) {
                $printService->createProductionJobs( $order, $createdItems);
            }

            return $order->fresh([
                'items.product',
                'table',
            ]);
        });
    }
}
