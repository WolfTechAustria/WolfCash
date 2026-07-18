<?php

namespace App\Printing;

use App\Models\Printer as PrinterModel;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer as EscPosPrinter;
use RuntimeException;
use Throwable;

class EscPosNetworkTransport implements PrintTransport
{
    public function print(
        PrinterModel $printer,
        RenderedPrint $document
    ): void {
        if (! $printer->is_enabled) {
            throw new RuntimeException(
                "Drucker {$printer->name} ist deaktiviert."
            );
        }

        $host = trim((string) $printer->ip_address);
        $port = (int) ($printer->port ?: 9100);
        $timeout = max(
            1,
            (int) config('printing.network_timeout', 5)
        );

        if ($host === '') {
            throw new RuntimeException(
                "Für Drucker {$printer->name} ist keine IP Adresse konfiguriert konfiguriert."
            );
        }

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException(
                "Für Drucker {$printer->name} ist ein ungültiger Port konfiguriert."
            );
        }

        $connector = null;
        $escPos = null;

        try {
            $connector = new NetworkPrintConnector(
                $host,
                $port,
                $timeout
            );

            $escPos = new EscPosPrinter($connector);

            $escPos->initialize();

            $escPos->setJustification(
                EscPosPrinter::JUSTIFY_CENTER
            );

            $escPos->setEmphasis(true);
            $escPos->setTextSize(2, 2);
            $escPos->text($document->title."\n");
            $escPos->setTextSize(1, 1);
            $escPos->setEmphasis(false);

            $escPos->feed();

            $escPos->setJustification(
                EscPosPrinter::JUSTIFY_LEFT
            );

            foreach ($document->lines as $line) {
                $escPos->text((string) $line."\n");
            }

            $escPos->feed(2);

            if ($document->openDrawer) {
                $escPos->pulse();
            }

            if ($document->cutPaper) {
                $escPos->cut();
            }
        } catch (Throwable $exception) {
            throw new RuntimeException(
                sprintf(
                    'Druck auf %s (%s:%d) fehlgeschlagen: %s',
                    $printer->name,
                    $host,
                    $port,
                    $exception->getMessage()
                ),
                previous: $exception
            );
        } finally {
            try {
                if ($escPos !== null) {
                    $escPos->close();
                } elseif ($connector !== null) {
                    $connector->finalize();
                }
            } catch (Throwable $closeException) {
                report($closeException);
            }
        }
    }
}
