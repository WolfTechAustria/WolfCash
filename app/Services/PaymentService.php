<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private readonly DailyClosingService $dailyClosingService,
        private readonly PaymentReceiptService $paymentReceiptService,
        private readonly StockService $stockService,
    ) {
    }

    /**
     * Bezahlt ausgewählte Mengen und erzeugt dafür genau
     * einen Zahlungsdatensatz samt Kundenbeleg.
     *
     * @param array<int|string, int> $selectedForPayment
     * @param array{name: ?string, address: ?string, vat_id: ?string}|null $invoiceRecipient
     */
    public function paySelection(
        Order $order,
        array $selectedForPayment,
        string $method,
        ?array $invoiceRecipient = null
    ): ?Payment {
        $this->dailyClosingService->assertOpen(today());

        return DB::transaction(function () use (
            $order,
            $selectedForPayment,
            $method,
            $invoiceRecipient
        ): ?Payment {
            $amount = 0.0;
            $receiptItems = [];
            $paidItemIds = [];

            foreach ($selectedForPayment as $itemId => $quantity) {
                $item = OrderItem::query()
                    ->with('product')
                    ->where('order_id', $order->id)
                    ->whereNull('paid_at')
                    ->lockForUpdate()
                    ->find($itemId);

                if (! $item) {
                    continue;
                }

                $openQuantity = (int) $item->open_quantity;

                $payQuantity = min(
                    max(0, (int) $quantity),
                    $openQuantity
                );

                if ($payQuantity <= 0) {
                    continue;
                }

                $unitPrice = (float) $item->price;
                $lineTotal = round(
                    $unitPrice * $payQuantity,
                    2
                );

                $amount += $lineTotal;

                /*
                 * Snapshot für genau diese Zahlung.
                 * Dieser wird später nicht aus den veränderten
                 * OrderItems rekonstruiert.
                 */
                $receiptItems[] = [
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name
                        ?? 'Unbekanntes Produkt',
                    'quantity' => $payQuantity,
                    'unit_price' => round($unitPrice, 2),
                    'total' => $lineTotal,
                    'note' => $item->note,
                ];

                /*
                 * Die gesamte noch offene Menge dieser Position
                 * wird bezahlt.
                 */
                if ($payQuantity >= $openQuantity) {
                    $item->update([
                        'paid_at' => now(),
                    ]);

                    $paidItemIds[] = $item->id;

                    continue;
                }

                /*
                 * Teilzahlung:
                 * Die bezahlte Menge wird als eigener, bereits
                 * bezahlter Datensatz abgespalten.
                 */
                $paidItemIds[] = OrderItem::create([
                    'order_id' => $item->order_id,
                    'product_id' => $item->product_id,
                    'quantity' => $payQuantity,
                    'price' => $item->price,
                    'note' => $item->note,
                    'status' => $item->status,
                    'paid_at' => now(),
                    'cancelled_quantity' => 0,

                    /*
                     * Diese Position bildet nur die Zahlungsaufteilung
                     * ab und darf keinen neuen Produktionsbon auslösen.
                     */
                    'production_status' =>
                        $item->production_status,

                    'production_completed_quantity' => min(
                        $payQuantity,
                        (int) $item->production_completed_quantity
                    ),

                    'production_printed_quantity' => min(
                        $payQuantity,
                        (int) $item->production_printed_quantity
                    ),
                ])->id;

                /*
                 * Stornierte Mengen verbleiben am ursprünglichen
                 * Datensatz. Abgezogen wird nur die bezahlte Menge.
                 */
                $item->update([
                    'quantity' =>
                        (int) $item->quantity - $payQuantity,
                ]);
            }

            if ($amount <= 0 || $receiptItems === []) {
                return null;
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => round($amount, 2),
                'payment_method' => $method,
                'user_id' => auth()->id(),
                'invoice_recipient_name' => $invoiceRecipient['name'] ?? null,
                'invoice_recipient_address' => $invoiceRecipient['address'] ?? null,
                'invoice_recipient_vat_id' => $invoiceRecipient['vat_id'] ?? null,
            ]);

            $this->finalizePayment($payment, $paidItemIds, $receiptItems);

            $this->paymentReceiptService->createAndDispatch(
                payment: $payment,
                order: $order,
                items: $receiptItems,
            );

            return $payment;
        });
    }

    /**
     * Bezahlt sämtliche noch offenen Positionen und erstellt
     * einen Beleg mit genau diesen Positionen.
     *
     * @param array{name: ?string, address: ?string, vat_id: ?string}|null $invoiceRecipient
     */
    public function payRemaining(
        Order $order,
        string $method,
        ?array $invoiceRecipient = null
    ): ?Payment {
        $this->dailyClosingService->assertOpen(today());

        return DB::transaction(function () use (
            $order,
            $method,
            $invoiceRecipient
        ): ?Payment {
            $openItems = $order->items()
                ->with('product')
                ->whereNull('paid_at')
                ->lockForUpdate()
                ->get()
                ->filter(
                    fn (OrderItem $item): bool =>
                        (int) $item->open_quantity > 0
                );

            $receiptItems = [];
            $amount = 0.0;
            $paidItemIds = [];

            foreach ($openItems as $item) {
                $payQuantity = (int) $item->open_quantity;
                $unitPrice = (float) $item->price;

                $lineTotal = round(
                    $unitPrice * $payQuantity,
                    2
                );

                $amount += $lineTotal;

                $receiptItems[] = [
                    'order_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'name' => $item->product?->name
                        ?? 'Unbekanntes Produkt',
                    'quantity' => $payQuantity,
                    'unit_price' => round($unitPrice, 2),
                    'total' => $lineTotal,
                    'note' => $item->note,
                ];

                $item->update([
                    'paid_at' => now(),
                ]);

                $paidItemIds[] = $item->id;
            }

            if ($amount <= 0 || $receiptItems === []) {
                return null;
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'amount' => round($amount, 2),
                'payment_method' => $method,
                'user_id' => auth()->id(),
                'invoice_recipient_name' => $invoiceRecipient['name'] ?? null,
                'invoice_recipient_address' => $invoiceRecipient['address'] ?? null,
                'invoice_recipient_vat_id' => $invoiceRecipient['vat_id'] ?? null,
            ]);

            $this->finalizePayment($payment, $paidItemIds, $receiptItems);

            $this->paymentReceiptService->createAndDispatch(
                payment: $payment,
                order: $order,
                items: $receiptItems,
            );

            return $payment;
        });
    }

    /**
     * @param  list<int>  $paidItemIds
     * @param  list<array{product_id: ?int, quantity: int}>  $receiptItems
     */
    private function finalizePayment(
        Payment $payment,
        array $paidItemIds,
        array $receiptItems
    ): void {
        OrderItem::query()
            ->whereKey($paidItemIds)
            ->update(['payment_id' => $payment->id]);

        /*
         * Ein eingelöster Bon wurde an der stationären Kassa bereits
         * verkauft und vom Bestand abgebucht; die erneute Abbuchung
         * durch die Tischbestellung wird hier ausgeglichen.
         */
        if ($payment->payment_method !== Payment::VOUCHER) {
            return;
        }

        foreach ($receiptItems as $receiptItem) {
            $this->stockService->restock(
                $receiptItem['product_id'],
                (int) $receiptItem['quantity']
            );
        }
    }
}
