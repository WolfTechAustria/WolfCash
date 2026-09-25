<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly DailyClosingService $dailyClosingService,
        private readonly StockService $stockService,
    ) {
    }

    public function createOrder(
        int $tableId,
        array $cart,
        ?PrintService $printService = null,
        bool $forceNewOrder = false,
        string $source = Order::SOURCE_POS,
        ?\Closure $onOrderCreated = null,
        ?string $reservationHolder = null,
    ): Order {
        $this->dailyClosingService->assertOpen(
            today()
        );

        return DB::transaction(function () use (
            $tableId,
            $cart,
            $printService,
            $forceNewOrder,
            $source,
            $onOrderCreated,
            $reservationHolder
        ): Order {
            $table = Table::findOrFail(
                $tableId
            );

            /*
             * Bestand endgültig abbuchen und Warenkorb-Reservierungen
             * auflösen. SelfOrders (forceNewOrder) sind bereits bezahlt,
             * dort wird bei Überbuchung nur geklemmt statt abgebrochen.
             */
            $this->stockService->consume(
                $cart,
                $reservationHolder,
                strict: ! $forceNewOrder,
            );

            $order = null;

            /*
             * Normale Kellnerbestellung:
             * bestehende offene Tisch-Order verwenden.
             *
             * SelfOrder:
             * immer eine neue eigenständige Order.
             */
            if (! $forceNewOrder) {
                $order = Order::query()
                    ->where(
                        'table_id',
                        $table->id
                    )
                    ->where(
                        'status',
                        Order::STATUS_OPEN
                    )
                    ->first();
            }

            if (! $order) {
                $order = Order::create([
                    'table_id' => $table->id,
                    'status' => Order::STATUS_OPEN,
                    'source' => $source,
                    'total' => 0,
                ]);
            }

            $createdItems = [];

            foreach ($cart as $item) {
                $quantity = (int) (
                    $item['quantity']
                    ?? 0
                );

                if ($quantity <= 0) {
                    continue;
                }

                $orderItem = OrderItem::create([
                    'order_id' =>
                        $order->id,

                    'product_id' =>
                        $item['id'],

                    'quantity' =>
                        $quantity,

                    'price' =>
                        $item['price'],

                    'note' =>
                        $item['note']
                        ?? null,

                    /*
                     * Bei normalen POS-Bestellungen nicht gesetzt.
                     *
                     * SelfOrder übergibt den bereits bestätigten
                     * Zahlungszeitpunkt.
                     */
                    'paid_at' =>
                        $item['paid_at']
                        ?? null,

                    'status' =>
                        OrderItem::STATUS_PENDING,

                    'production_status' =>
                        OrderItem::PRODUCTION_PENDING,

                    'production_completed_quantity' =>
                        0,

                    'production_printed_quantity' =>
                        0,
                ]);

                $createdItems[] =
                    $orderItem;
            }

            $order->recalculateTotal();

            $table->update([
                'status' => 'occupied',
            ]);

            /*
             * Produktionsbon auch bei bereits bezahlten
             * SelfOrder-Positionen erzeugen.
             */
            if (
                $printService
                && $createdItems !== []
            ) {
                $printService
                    ->createProductionJobs(
                        $order,
                        $createdItems
                    );
            }

            /*
             * Alternativer Druckweg für Bestellquellen, die keinen
             * Produktionsbon an den Stationen wollen (z. B. stationäre
             * Selbstbedienungskassen), sondern ein eigenes Ticket.
             */
            if (
                $onOrderCreated
                && $createdItems !== []
            ) {
                $onOrderCreated($order, $createdItems);
            }

            return $order->fresh([
                'items.product',
                'table',
            ]);
        });
    }
}
