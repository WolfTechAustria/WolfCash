<?php

namespace App\Printing;

use App\Models\Printer;
use Illuminate\Support\Facades\Log;

class SimulationPrintTransport implements PrintTransport
{
    public function print(
        Printer $printer,
        RenderedPrint $document
    ): void {
        Log::channel('single')->info(
            'Simulierter Bondruck',
            [
                'printer_id' => $printer->id,
                'printer_name' => $printer->name,
                'title' => $document->title,
                'header_lines' => $document->headerLines,
                'lines' => $document->lines,
                'cut_paper' => $document->cutPaper,
                'open_drawer' => $document->openDrawer,
            ]
        );
    }
}
