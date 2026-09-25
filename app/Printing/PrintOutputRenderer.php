<?php

namespace App\Printing;

use App\Models\PrintOutput;
use App\Models\Setting;
use RuntimeException;

class PrintOutputRenderer
{
    public function render(PrintOutput $output): RenderedPrint
    {
        $output->loadMissing([
            'printJob.order.table',
            'printJob.productionStation',
            'orderItem.product',
        ]);

        if (! $output->printJob) {
            throw new RuntimeException(
                "PrintOutput #{$output->id} besitzt keinen PrintJob."
            );
        }

        return match ($output->type) {
            PrintOutput::TYPE_CANCELLATION =>
            $this->renderCancellation($output),

            PrintOutput::TYPE_RECEIPT =>
            $this->renderReceipt($output),

            default =>
            $this->renderProduction($output),
        };
    }

    private function renderProduction(PrintOutput $output): RenderedPrint
    {
        $payload = $output->payload ?? [];

        $tableNumber =
            $output->printJob?->order?->table?->number
            ?? $payload['table']
            ?? '–';

        $name = $payload['name']
            ?? $output->orderItem?->product?->name
            ?? 'Unbekanntes Produkt';

        $quantity = max(
            1,
            (int) (
                $payload['quantity']
                ?? $output->quantity
            )
        );

        $note = $payload['note']
            ?? $output->orderItem?->note;

        $lines = [
            'TISCH '.$tableNumber,
        ];

        if ($output->printJob->productionStation) {
            $lines[] = strtoupper(
                $output->printJob->productionStation->name
            );
        }

        $lines[] = $output->created_at
            ->format('d.m.Y H:i');

        $lines[] = str_repeat('-', 32);

        $lines[] = $quantity.'x '.$name;

        if ($note) {
            $lines[] = '  > '.$note;
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'Bon #'.$output->print_job_id;
        $lines[] = 'Ausgabe #'.$output->id;

        return new RenderedPrint(
            title: 'Produktionsbon',
            lines: $lines,
            cutPaper: true,
        );
    }

    private function renderCancellation(PrintOutput $output): RenderedPrint
    {
        $payload = $output->payload ?? [];

        $tableNumber =
            $output->printJob?->order?->table?->number
            ?? $payload['table']
            ?? '–';

        $name = $payload['name']
            ?? $output->orderItem?->product?->name
            ?? 'Unbekanntes Produkt';

        $quantity = max(
            1,
            (int) (
                $payload['quantity']
                ?? $output->quantity
            )
        );

        $reason = trim(
            (string) (
                $payload['reason']
                ?? 'Kein Grund angegeben'
            )
        );

        $note = $payload['note']
            ?? $output->orderItem?->note;

        $lines = [
            '*** STORNO ***',
            '',
            'TISCH '.$tableNumber,
        ];

        if ($output->printJob->productionStation) {
            $lines[] = strtoupper(
                $output->printJob->productionStation->name
            );
        }

        $lines[] = $output->created_at
            ->format('d.m.Y H:i');

        $lines[] = str_repeat('=', 32);

        $lines[] = $quantity.'x '.$name;

        if ($note) {
            $lines[] = '  > '.$note;
        }

        $lines[] = str_repeat('-', 32);
        $lines[] = 'Grund: '.$reason;

        if (! empty($payload['cancelled_by_name'])) {
            $lines[] =
                'Durch: '.$payload['cancelled_by_name'];
        }

        $lines[] = str_repeat('=', 32);
        $lines[] = 'Originalbon #'.$output->print_job_id;
        $lines[] = 'Stornoausgabe #'.$output->id;

        return new RenderedPrint(
            title: 'Stornobon',
            lines: $lines,
            cutPaper: true,
        );
    }

    private function renderReceipt(PrintOutput $output): RenderedPrint
    {
        $payload = $output->payload ?? [];

        $items = $payload['items'] ?? [];

        $receiptNumber =
            $payload['receipt_number']
            ?? $payload['payment_id']
            ?? $output->id;

        $orderId =
            $payload['order_id']
            ?? $output->printJob?->order_id
            ?? '–';

        $tableNumber =
            $payload['table_number']
            ?? $output->printJob?->order?->table?->number
            ?? '–';

        $paymentMethod = match (
            $payload['payment_method'] ?? ''
        ) {
            'cash' => 'Bar',
            'card' => 'Karte',
            'voucher' => 'Bon/Gutschein',
            'invoice' => 'Rechnung',
            'house' => 'Auf Haus',
            default => ucfirst(
                (string) (
                    $payload['payment_method']
                    ?? 'Unbekannt'
                )
            ),
        };

        $paidAt = isset($payload['paid_at'])
            ? \Carbon\Carbon::parse(
                $payload['paid_at']
            )->format('d.m.Y H:i')
            : $output->created_at->format('d.m.Y H:i');

        $amount = (float) (
            $payload['amount'] ?? 0
        );

        /*
         * Überschrift und Vortext kommen aus den Einstellungen des
         * jeweiligen Veranstalters. Ohne eigene Überschrift bleibt
         * "Zahlungsbeleg" der große Titel.
         */
        $title = Setting::receiptTitle();
        $intro = Setting::receiptIntro();

        $headerLines = $intro === ''
            ? []
            : array_map('rtrim', preg_split('/\r\n|\r|\n/', $intro));

        if ($title !== '') {
            if ($headerLines !== []) {
                $headerLines[] = '';
            }

            $headerLines[] = 'Zahlungsbeleg';
        }

        $lines = [
            str_repeat('=', 32),

            'Beleg: #'.$receiptNumber,
            'Bestellung: #'.$orderId,
            'Tisch: '.$tableNumber,
            'Datum: '.$paidAt,

            str_repeat('-', 32),
        ];

        $invoiceRecipient = $payload['invoice_recipient'] ?? null;

        if (! empty($invoiceRecipient)) {
            $lines[] = 'Rechnungsempfänger:';

            if (! empty($invoiceRecipient['name'])) {
                $lines[] = $invoiceRecipient['name'];
            }

            if (! empty($invoiceRecipient['address'])) {
                foreach (
                    preg_split('/\r\n|\r|\n/', (string) $invoiceRecipient['address'])
                    as $addressLine
                ) {
                    $lines[] = $addressLine;
                }
            }

            if (! empty($invoiceRecipient['vat_id'])) {
                $lines[] = 'UID: '.$invoiceRecipient['vat_id'];
            }

            $lines[] = str_repeat('-', 32);
        }

        foreach ($items as $item) {
            $quantity = max(
                1,
                (int) ($item['quantity'] ?? 1)
            );

            $name = trim(
                (string) (
                    $item['name']
                    ?? 'Unbekanntes Produkt'
                )
            );

            $unitPrice = (float) (
                $item['unit_price'] ?? 0
            );

            $lineTotal = (float) (
                $item['total']
                ?? ($unitPrice * $quantity)
            );

            $lines[] = $quantity.'x '.$name;

            $lines[] = sprintf(
                '  %s x %s EUR',
                number_format(
                    $quantity,
                    0,
                    ',',
                    '.'
                ),
                number_format(
                    $unitPrice,
                    2,
                    ',',
                    '.'
                )
            );

            $lines[] = sprintf(
                '  Summe: %s EUR',
                number_format(
                    $lineTotal,
                    2,
                    ',',
                    '.'
                )
            );

            if (! empty($item['note'])) {
                $lines[] = '  > '.$item['note'];
            }
        }

        $lines[] = str_repeat('=', 32);

        $lines[] = sprintf(
            'GESAMT: %s EUR',
            number_format(
                $amount,
                2,
                ',',
                '.'
            )
        );

        $lines[] = 'Zahlungsart: '.$paymentMethod;

        $lines[] = str_repeat('=', 32);

        $lines[] = '';
        $lines[] = 'Vielen Dank!';
        $lines[] = '';

        return new RenderedPrint(
            title: $title !== '' ? $title : 'Zahlungsbeleg',
            lines: $lines,
            cutPaper: true,
            headerLines: $headerLines,
        );
    }

}
