<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{

    public function __construct(private readonly DailyClosingService $dailyClosingService) {
    }
    public function paySelection(Order $order, array $selectedForPayment, string $method): void
    {
        $this->dailyClosingService->assertOpen(today());

        DB::transaction(function () use (
            $order,
            $selectedForPayment,
            $method
        ): void {
            $amount = 0.0;

            foreach ($selectedForPayment as $itemId => $quantity) {
                $item = OrderItem::query()
                    ->where('order_id', $order->id)
                    ->whereNull('paid_at')
                    ->lockForUpdate()
                    ->find($itemId);

                if (! $item) {
                    continue;
                }

                $openQuantity = $item->open_quantity;

                $payQuantity = min(
                    max(0, (int) $quantity),
                    $openQuantity
                );

                if ($payQuantity <= 0) {
                    continue;
                }

                $amount +=
                    (float) $item->price * $payQuantity;

                /*
                 * Alle noch verrechenbaren Stücke dieser Position
                 * werden bezahlt.
                 *
                 * Eine eventuell stornierte Teilmenge bleibt
                 * historisch am Datensatz erhalten.
                 */
                if ($payQuantity >= $openQuantity) {
                    $item->update([
                        'paid_at' => now(),
                    ]);

                    continue;
                }

                /*
                 * Teilzahlung:
                 * bezahlte Menge wird als eigener Datensatz
                 * abgespalten.
                 */
                OrderItem::create([
                    'order_id' => $item->order_id,
                    'product_id' => $item->product_id,
                    'quantity' => $payQuantity,
                    'price' => $item->price,
                    'note' => $item->note,
                    'status' => $item->status,
                    'paid_at' => now(),

                    /*
                     * Der abgespaltene Datensatz repräsentiert
                     * ausschließlich tatsächlich bezahlte Stücke.
                     */
                    'cancelled_quantity' => 0,

                    /*
                     * Diese Position darf keinen neuen Küchenprozess
                     * auslösen. Sie ist nur eine Zahlungsaufteilung.
                     */
                    'production_status' =>
                        $item->production_status,

                    'production_completed_quantity' =>
                        min(
                            $payQuantity,
                            $item->production_completed_quantity
                        ),

                    'production_printed_quantity' =>
                        min(
                            $payQuantity,
                            $item->production_printed_quantity
                        ),
                ]);

                /*
                 * Die stornierte Menge bleibt auf dem ursprünglichen
                 * Datensatz. Nur die bezahlte aktive Menge wird
                 * von quantity abgezogen.
                 */
                $item->update([
                    'quantity' =>
                        $item->quantity - $payQuantity,
                ]);
            }

            if ($amount <= 0) {
                return;
            }

            Payment::create([
                'order_id' => $order->id,
                'amount' => round($amount, 2),
                'payment_method' => $method,
            ]);
        });
    }

    public function payRemaining(Order $order, string $method): void
    {
        $this->dailyClosingService->assertOpen(today());

        DB::transaction(function () use (
            $order,
            $method
        ): void {
            $openItems = $order->items()
                ->whereNull('paid_at')
                ->lockForUpdate()
                ->get()
                ->filter(
                    fn (OrderItem $item) =>
                        $item->open_quantity > 0
                );

            $amount = $openItems->sum(
                fn (OrderItem $item) =>
                    (float) $item->price
                    * $item->open_quantity
            );

            if ($amount <= 0) {
                return;
            }

            foreach ($openItems as $item) {
                $item->update([
                    'paid_at' => now(),
                ]);
            }

            Payment::create([
                'order_id' => $order->id,
                'amount' => round($amount, 2),
                'payment_method' => $method,
            ]);
        });
    }
}
