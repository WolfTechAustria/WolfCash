<?php

namespace App\Services;

use App\Jobs\ProcessPrintOutput;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Printer;
use App\Models\PrintJob;
use App\Models\PrintOutput;
use App\Models\Setting;
use RuntimeException;

class PaymentReceiptService
{
    /**
     * Erzeugt für eine Zahlung einen unveränderlichen
     * Zahlungsbeleg-Snapshot.
     *
     * Wenn der automatische Belegdruck deaktiviert ist,
     * wird nur der PrintJob gespeichert. Es wird noch
     * kein PrintOutput erzeugt.
     *
     * @param array<int, array{
     *     order_item_id: int|null,
     *     product_id: int|null,
     *     name: string,
     *     quantity: int,
     *     unit_price: float,
     *     total: float,
     *     note: string|null
     * }> $items
     */
    public function createAndDispatch(
        Payment $payment,
        Order $order,
        array $items
    ): PrintJob {
        /*
         * Für jede Zahlung darf nur ein ursprünglicher
         * Belegjob existieren.
         */
        $existingJob = PrintJob::query()
            ->where('payment_id', $payment->id)
            ->where('type', PrintJob::TYPE_RECEIPT)
            ->first();

        if ($existingJob) {
            return $existingJob;
        }

        $automaticPrintingEnabled =
            Setting::automaticReceiptPrintingEnabled();

        /*
         * Bei deaktiviertem automatischem Druck versuchen wir,
         * den Drucker bereits am Job zu hinterlegen.
         *
         * Fehlt die Konfiguration, darf die Zahlung trotzdem
         * erfolgreich abgeschlossen werden.
         */
        $order->loadMissing('table');

        $printer = $automaticPrintingEnabled
            ? $this->receiptPrinter($order)
            : $this->configuredReceiptPrinterOrNull($order);

        $invoiceRecipient = array_filter([
            'name' => $payment->invoice_recipient_name,
            'address' => $payment->invoice_recipient_address,
            'vat_id' => $payment->invoice_recipient_vat_id,
        ]);

        $payload = [
            'version' => 1,

            'receipt_number' => $payment->id,

            'payment_id' => $payment->id,

            'order_id' => $order->id,

            'table_number' =>
                $order->table?->number,

            'payment_method' =>
                $payment->payment_method,

            'amount' => round(
                (float) $payment->amount,
                2
            ),

            'paid_at' =>
                $payment->created_at?->toIso8601String()
                ?? now()->toIso8601String(),

            /*
             * Die Positionen gehören exakt zu dieser Zahlung.
             * Sie werden später nicht aus den möglicherweise
             * veränderten OrderItems rekonstruiert.
             */
            'items' => array_values($items),

            'invoice_recipient' =>
                $invoiceRecipient !== []
                    ? $invoiceRecipient
                    : null,

            'automatic_print_suppressed' =>
                ! $automaticPrintingEnabled,

            'suppressed_at' =>
                ! $automaticPrintingEnabled
                    ? now()->toIso8601String()
                    : null,
        ];

        $printJob = PrintJob::create([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'printer_id' => $printer?->id,
            'production_station_id' => null,
            'type' => PrintJob::TYPE_RECEIPT,
            'status' => PrintJob::STATUS_PENDING,
            'payload' => $payload,
            'ready_to_print' =>
                $automaticPrintingEnabled,
        ]);

        /*
         * Der Snapshot wurde gespeichert, aber der Ausdruck
         * soll nicht automatisch ausgelöst werden.
         */
        if (! $automaticPrintingEnabled) {
            return $printJob;
        }

        $output = PrintOutput::create([
            'print_job_id' => $printJob->id,
            'order_item_id' => null,
            'printer_id' => $printer->id,
            'quantity' => 1,
            'type' => PrintOutput::TYPE_RECEIPT,
            'status' => PrintOutput::STATUS_PENDING,
            'payload' => array_merge(
                $payload,
                [
                    'manual_print' => false,
                    'is_copy' => false,
                ]
            ),
        ]);

        /*
         * Der Druckjob wird erst nach erfolgreichem Commit
         * der Zahlung an die Queue übergeben.
         */
        ProcessPrintOutput::dispatch(
            $output->id
        )->afterCommit();

        return $printJob;
    }

    /**
     * Druckt einen bereits gespeicherten Zahlungsbeleg manuell.
     *
     * Wenn bisher noch kein Ausdruck erfolgreich gedruckt wurde,
     * gilt der manuelle Ausdruck als Erstausdruck.
     *
     * Erst weitere Ausdrucke werden als Kopie gekennzeichnet.
     */
    public function reprint(
        Payment $payment
    ): PrintOutput {
        if (! Setting::receiptReprintingEnabled()) {
            throw new RuntimeException(
                'Der manuelle Zahlungsbelegdruck ist in den '
                .'globalen Einstellungen deaktiviert.'
            );
        }

        $printJob = PrintJob::query()
            ->with([
                'printer',
                'order.table',
                'outputs',
            ])
            ->where('payment_id', $payment->id)
            ->where('type', PrintJob::TYPE_RECEIPT)
            ->first();

        if (! $printJob) {
            throw new RuntimeException(
                'Für diese Zahlung wurde kein Zahlungsbeleg gefunden.'
            );
        }

        $payload = $printJob->payload ?? [];

        if ($payload === []) {
            throw new RuntimeException(
                'Der gespeicherte Zahlungsbeleg enthält '
                .'keinen Druck-Snapshot.'
            );
        }

        /*
         * Falls beim Erstellen des Belegjobs kein Drucker
         * gespeichert wurde, verwenden wir den aktuell
         * konfigurierten Belegdrucker.
         */
        $printer = $printJob->printer
            ?? $this->receiptPrinter($printJob->order);

        if (! $printer->is_active) {
            throw new RuntimeException(
                'Der Belegdrucker ist nicht aktiv.'
            );
        }

        /*
         * Wurde bereits mindestens ein Beleg erfolgreich gedruckt,
         * handelt es sich bei diesem Ausdruck um eine Kopie.
         *
         * Gibt es nur fehlgeschlagene oder noch ausstehende
         * Ausgaben, bleibt der Ausdruck ein Erstausdruck.
         */
        $hasPrintedOutput = $printJob
            ->outputs
            ->contains(
                fn (PrintOutput $output): bool =>
                    $output->status
                    === PrintOutput::STATUS_PRINTED
            );

        $manualPayload = array_merge(
            $payload,
            [
                'manual_print' => true,

                'is_copy' => $hasPrintedOutput,

                /*
                 * Rückwärtskompatibilität zum bisherigen Renderer.
                 */
                'reprint' => $hasPrintedOutput,

                'printed_on_request_at' =>
                    now()->toIso8601String(),

                'reprinted_at' =>
                    now()->toIso8601String(),

                'original_print_job_id' =>
                    $printJob->id,
            ]
        );

        /*
         * Falls der Drucker im ursprünglichen Job fehlte,
         * tragen wir ihn dauerhaft nach.
         */
        if (! $printJob->printer_id) {
            $printJob->update([
                'printer_id' => $printer->id,
            ]);
        }

        $output = PrintOutput::create([
            'print_job_id' => $printJob->id,
            'order_item_id' => null,
            'printer_id' => $printer->id,
            'quantity' => 1,
            'type' => PrintOutput::TYPE_RECEIPT,
            'status' => PrintOutput::STATUS_PENDING,
            'payload' => $manualPayload,
        ]);

        ProcessPrintOutput::dispatch(
            $output->id
        )->afterCommit();

        return $output;
    }

    /**
     * Gibt den aktiven, verpflichtend konfigurierten
     * Drucker für Zahlungsbelege zurück.
     */
    /**
     * Stationäre Kassen mit eigenem Drucker drucken ihre Belege dort,
     * alle anderen auf dem Belegdrucker aus den Einstellungen.
     */
    private function receiptPrinterIdFor(?Order $order): int
    {
        $table = $order?->table;

        if ($table?->is_stationary && $table->printer_id) {
            return (int) $table->printer_id;
        }

        return Setting::receiptPrinterId();
    }

    private function receiptPrinter(?Order $order = null): Printer
    {
        $printerId = $this->receiptPrinterIdFor($order);

        if ($printerId <= 0) {
            throw new RuntimeException(
                'Es ist kein Drucker für Zahlungsbelege '
                .'konfiguriert. Bitte in den Einstellungen '
                .'einen Belegdrucker wählen.'
            );
        }

        $printer = Printer::query()
            ->whereKey($printerId)
            ->where('is_active', true)
            ->first();

        if (! $printer) {
            throw new RuntimeException(
                'Der konfigurierte Belegdrucker wurde nicht '
                .'gefunden oder ist nicht aktiv.'
            );
        }

        return $printer;
    }

    /**
     * Versucht den konfigurierten Drucker zu laden,
     * ohne bei fehlender Konfiguration einen Fehler auszulösen.
     *
     * Das wird benötigt, wenn der automatische Belegdruck
     * deaktiviert ist. Eine Zahlung darf dann auch ohne
     * erreichbaren Drucker funktionieren.
     */
    private function configuredReceiptPrinterOrNull(?Order $order = null): ?Printer
    {
        $printerId = $this->receiptPrinterIdFor($order);

        if ($printerId <= 0) {
            return null;
        }

        return Printer::query()
            ->whereKey($printerId)
            ->first();
    }
}
