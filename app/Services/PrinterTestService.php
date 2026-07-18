<?php

namespace App\Services;

use App\Models\Printer;
use App\Printing\PrintTransport;
use App\Printing\RenderedPrint;

class PrinterTestService
{
    public function __construct(
        private readonly PrintTransport $transport,
    ) {
    }

    public function printTest(Printer $printer): void
    {
        $document = new RenderedPrint(
            title: 'TESTDRUCK',
            lines: [
                '',
                'WolfCash POS',
                '',
                'Der Drucker funktioniert',
                '',
                'Datum: '.now()->format('d.m.Y'),
                'Uhrzeit: '.now()->format('H:i:s'),
                '',
                'Drucker:',
                $printer->name,
                '',
                str_repeat('-', 32),
                'Vielen Dank!',
            ],
            cutPaper: true,
        );

        $this->transport->print($printer, $document);
    }
}
