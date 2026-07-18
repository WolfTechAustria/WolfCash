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

        if (! $printer->host) {
            throw new RuntimeException(
                "Für Drucker {$printer->name} ist kein Host konfiguriert."
            );
        }

        $connector = null;
        $escPos = null;

        try {
            $connector = new NetworkPrintConnector(
                $printer->host,
                $printer->port,
                config('printing.network_timeout', 5)
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
                $escPos->text($line."\n");
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
                    'Druck auf %s fehlgeschlagen: %s',
                    $printer->name,
                    $exception->getMessage()
                ),
                previous: $exception
            );
        } finally {
            if ($escPos) {
                $escPos->close();
            } elseif ($connector) {
                $connector->finalize();
            }
        }
    }
}
