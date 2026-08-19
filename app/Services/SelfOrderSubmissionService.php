<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SelfOrder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SelfOrderSubmissionService
{
    public function __construct(
        private readonly OrderService $orderService,
        private readonly PrintService $printService,
        private readonly PaymentReceiptService $paymentReceiptService,
    ) {
    }

    public function submit(
        SelfOrder $selfOrder
    ): SelfOrder {
        return DB::transaction(function () use ($selfOrder): SelfOrder {

            /*
             * SelfOrder sperren.
             *
             * Stripe kann denselben Webhook mehrfach schicken.
             */
            $lockedSelfOrder = SelfOrder::query()
                ->with('items')
                ->whereKey($selfOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            /*
             * Bereits vollständig übernommen?
             *
             * Dann nichts ein zweites Mal erzeugen.
             */
            if (
                $lockedSelfOrder->status
                === SelfOrder::STATUS_SUBMITTED
            ) {
                return $lockedSelfOrder;
            }

            /*
             * Nur tatsächlich bezahlte SelfOrders dürfen
             * überhaupt in das Kassensystem gelangen.
             */
            if (
                $lockedSelfOrder->status
                !== SelfOrder::STATUS_PAID
            ) {
                throw new RuntimeException(
                    'SelfOrder ist noch nicht bezahlt.'
                );
            }

            if (
                $lockedSelfOrder->paid_at === null
            ) {
                throw new RuntimeException(
                    'SelfOrder besitzt keinen Zahlungszeitpunkt.'
                );
            }

            if ($lockedSelfOrder->items->isEmpty()) {
                throw new RuntimeException(
                    'SelfOrder enthält keine Positionen.'
                );
            }

            /*
             * Betrag nochmals anhand unseres eingefrorenen
             * Snapshots überprüfen.
             *
             * Intern in Cent rechnen.
             */
            $calculatedAmountCents = 0;

            foreach ($lockedSelfOrder->items as $item) {
                $unitPriceCents = (int) round(
                    (float) $item->unit_price * 100
                );

                $calculatedAmountCents +=
                    $unitPriceCents
                    * (int) $item->quantity;
            }

            $expectedAmountCents = (int) round(
                (float) $lockedSelfOrder->amount * 100
            );

            if (
                $calculatedAmountCents
                !== $expectedAmountCents
            ) {
                throw new RuntimeException(
                    'Der SelfOrder-Betrag stimmt nicht '
                    .'mit den Positionen überein.'
                );
            }

            /*
             * Aus dem eingefrorenen SelfOrder-Snapshot
             * das Cart-Format des bestehenden OrderService bauen.
             */
            $cart = [];

            foreach ($lockedSelfOrder->items as $item) {

                /*
                 * OrderItem benötigt aktuell eine gültige product_id.
                 */
                if (! $item->product_id) {
                    throw new RuntimeException(
                        'Ein Produkt der bezahlten SelfOrder '
                        .'existiert nicht mehr.'
                    );
                }

                $cart[] = [
                    'id' =>
                        $item->product_id,

                    'quantity' =>
                        (int) $item->quantity,

                    /*
                     * Wichtig:
                     * eingefrorenen Preis verwenden,
                     * NICHT den aktuellen Produktpreis.
                     */
                    'price' =>
                        (float) $item->unit_price,

                    'note' =>
                        $item->note,

                    /*
                     * Diese Position wurde bereits durch Stripe
                     * bezahlt.
                     */
                    'paid_at' =>
                        $lockedSelfOrder->paid_at,
                ];
            }

            /*
             * Ab hier verwenden wir exakt denselben OrderService
             * wie beim Kellner.
             *
             * Dadurch entstehen:
             *
             * - echte Order / bestehende offene Order
             * - OrderItems
             * - Produktions-PrintJobs
             *
             * und NUR jetzt, nach erfolgreicher Zahlung.
             */
            $order = $this->orderService->createOrder(
                $lockedSelfOrder->table_id,
                $cart,
                $this->printService
            );

            /*
             * Jetzt den normalen WolfCash-Zahlungsdatensatz anlegen.
             *
             * Für den ersten Stripe-SelfOrder-Flow behandeln wir
             * Stripe-Zahlungen als Kartenzahlung.
             */
            $payment = Payment::create([
                'order_id' =>
                    $order->id,

                'amount' =>
                    $lockedSelfOrder->amount,

                'payment_method' =>
                    Payment::CARD,

                /*
                 * Kein Kellner / kein WolfCash-User.
                 */
                'device_id' =>
                    null,

                'user_id' =>
                    null,
            ]);

            /*
             * Unveränderlichen Beleg-Snapshot erzeugen.
             *
             * order_item_id kann laut bestehendem
             * PaymentReceiptService null sein.
             */
            $receiptItems = [];

            foreach ($lockedSelfOrder->items as $item) {
                $unitPrice =
                    round(
                        (float) $item->unit_price,
                        2
                    );

                $receiptItems[] = [
                    'order_item_id' =>
                        null,

                    'product_id' =>
                        $item->product_id,

                    'name' =>
                        $item->name,

                    'quantity' =>
                        (int) $item->quantity,

                    'unit_price' =>
                        $unitPrice,

                    'total' =>
                        round(
                            $unitPrice
                            * (int) $item->quantity,
                            2
                        ),

                    'note' =>
                        $item->note,
                ];
            }

            $this->paymentReceiptService
                ->createAndDispatch(
                    payment: $payment,
                    order: $order,
                    items: $receiptItems,
                );

            /*
             * Erst ganz am Schluss markieren.
             *
             * Sollte vorher irgendetwas fehlschlagen,
             * rollt die gesamte DB-Transaktion zurück.
             */
            $lockedSelfOrder->update([
                'order_id' =>
                    $order->id,

                'status' =>
                    SelfOrder::STATUS_SUBMITTED,

                'submitted_at' =>
                    now(),
            ]);

            return $lockedSelfOrder->fresh([
                'items',
                'order',
            ]);
        });
    }
}
