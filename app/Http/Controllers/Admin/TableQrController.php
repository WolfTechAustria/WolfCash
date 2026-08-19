<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Table;
use App\Services\TableOrderSessionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class TableQrController extends Controller
{
    public function pdf(
        Table $table,
        TableOrderSessionService $sessionService
    ) {
        $result =
            $sessionService->getOrCreate(
                $table
            );

        $qrSvg = (string) QrCode::format('svg')
            ->size(700)
            ->margin(2)
            ->errorCorrection('M')
            ->generate(
                $result['url']
            );

        $qrDataUri =
            'data:image/svg+xml;base64,'
            .base64_encode($qrSvg);

        $pdf = Pdf::loadView(
            'admin.tables.qr-pdf',
            [
                'table' =>
                    $table,

                'qrDataUri' =>
                    $qrDataUri,

                'url' =>
                    $result['url'],

                'title' =>
                    Setting::selfOrderingTitle(),

                'subtitle' =>
                    Setting::selfOrderingSubtitle(),
            ]
        );

        /*
         * A6 Hochformat.
         * Sehr praktisch als Tischaufsteller.
         */
        $pdf->setPaper(
            'a6',
            'portrait'
        );

        return $pdf->download(
            'wolfcash-tisch-'
            .$table->number
            .'.pdf'
        );
    }


    public function png(
        Table $table,
        TableOrderSessionService $sessionService
    ): Response {
        $result =
            $sessionService->getOrCreate(
                $table
            );

        /*
         * Hier erzeugen wir erstmal den reinen
         * hochauflösenden QR-Code als PNG.
         *
         * Die vollständige gestaltete Tischkarte
         * als PNG bauen wir im nächsten Schritt,
         * wenn PDF und QR-Ausgabe sauber laufen.
         */
        $png = QrCode::format('png')
            ->size(1200)
            ->margin(3)
            ->errorCorrection('M')
            ->generate(
                $result['url']
            );

        return response(
            $png,
            200,
            [
                'Content-Type' =>
                    'image/png',

                'Content-Disposition' =>
                    'attachment; filename="wolfcash-tisch-'
                    .$table->number
                    .'-qr.png"',
            ]
        );
    }
}
