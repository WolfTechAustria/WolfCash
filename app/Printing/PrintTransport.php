<?php

namespace App\Printing;

use App\Models\Printer;

interface PrintTransport
{
    public function print(
        Printer $printer,
        RenderedPrint $document
    ): void;
}
