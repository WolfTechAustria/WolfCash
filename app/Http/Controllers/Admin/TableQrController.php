<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\Table;
use App\Services\TableOrderSessionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use F9WebLtd\QrCode\Facades\QrCode;

class TableQrController extends Controller
{
    public function pdf(
        Table $table,
        TableOrderSessionService $sessionService
    ) {
        $result = $sessionService->getOrCreate($table);

        $qrPng = QrCode::format('png')
            ->size(700)
            ->margin(2)
            ->errorCorrection('M')
            ->generate($result['url']);

        $qrDataUri =
            'data:image/png;base64,'
            . base64_encode($qrPng);

        $pdf = Pdf::loadView(
            'admin.tables.qr-pdf',
            [
                'table' => $table,
                'qrDataUri' => $qrDataUri,
                'url' => $result['url'],
                'title' => Setting::selfOrderingTitle(),
                'subtitle' => Setting::selfOrderingSubtitle(),
            ]
        );

        $pdf->setPaper('a6', 'portrait');

        return $pdf->download(
            'wolfcash-tisch-'.$table->number.'.pdf'
        );
    }


    public function png(
        Table $table,
        TableOrderSessionService $sessionService
    ): Response {
        $result = $sessionService->getOrCreate($table);

        $title = Setting::selfOrderingTitle();

        $subtitle = Setting::selfOrderingSubtitle();

        /*
         * QR-Code hochauflösend erzeugen.
         */
        $qrPng = QrCode::format('png')
            ->size(1000)
            ->margin(2)
            ->errorCorrection('M')
            ->generate($result['url']);

        /*
         * A6 ungefähr bei 300 DPI:
         * 105 x 148 mm
         * = ca. 1240 x 1748 Pixel
         */
        $width = 1240;
        $height = 1748;

        $canvas = new \Imagick();

        $canvas->newImage(
            $width,
            $height,
            new \ImagickPixel('white')
        );

        $canvas->setImageFormat('png');

        /*
         * QR einlesen.
         */
        $qr = new \Imagick();

        $qr->readImageBlob($qrPng);

        $qrSize = 850;

        $qr->resizeImage(
            $qrSize,
            $qrSize,
            \Imagick::FILTER_LANCZOS,
            1
        );

        /*
         * Text-Renderer.
         */
        $draw = new \ImagickDraw();

        $draw->setFillColor(
            new \ImagickPixel('#111111')
        );

        $draw->setTextAlignment(
            \Imagick::ALIGN_CENTER
        );

        /*
         * Marke
         */
        $draw->setFontSize(34);

        $canvas->annotateImage(
            $draw,
            $width / 2,
            110,
            0,
            'WOLFCASH'
        );

        /*
         * Überschrift
         */
        $draw->setFontSize(64);

        $canvas->annotateImage(
            $draw,
            $width / 2,
            240,
            0,
            $title
        );

        /*
         * Tisch
         */
        $draw->setFontSize(44);

        $canvas->annotateImage(
            $draw,
            $width / 2,
            330,
            0,
            'Tisch '.$table->number
        );

        /*
         * QR-Code mittig einsetzen.
         */
        $qrX = (int) (($width - $qrSize) / 2);

        $qrY = 400;

        $canvas->compositeImage(
            $qr,
            \Imagick::COMPOSITE_OVER,
            $qrX,
            $qrY
        );

        /*
         * Untertitel
         */
        $draw->setFontSize(40);

        $canvas->annotateImage(
            $draw,
            $width / 2,
            1370,
            0,
            $subtitle
        );

        /*
         * Hinweis
         */
        $draw->setFillColor(
            new \ImagickPixel('#666666')
        );

        $draw->setFontSize(26);

        $canvas->annotateImage(
            $draw,
            $width / 2,
            1445,
            0,
            'QR-Code scannen und direkt bestellen'
        );

        /*
         * Tisch-ID klein unten.
         */
        $draw->setFillColor(
            new \ImagickPixel('#999999')
        );

        $draw->setFontSize(20);

        $canvas->annotateImage(
            $draw,
            $width / 2,
            1650,
            0,
            'Tisch '.$table->number
        );

        $content = $canvas->getImagesBlob();

        $qr->clear();
        $qr->destroy();

        $canvas->clear();
        $canvas->destroy();

        return response(
            $content,
            200,
            [
                'Content-Type' =>
                    'image/png',

                'Content-Disposition' =>
                    'attachment; filename="wolfcash-tisch-'
                    .$table->number
                    .'.png"',
            ]
        );
    }

    public function pdfAll()
    {
        $tables = Table::query()
            ->where('self_order_enabled', true)
            ->orderBy('number')
            ->get();

        $cards = [];

        foreach ($tables as $table) {

            /*
             * Wichtig:
             * Bestehende Session nur lesen.
             * Hier KEIN getOrCreate(), damit ein alter QR
             * nicht versehentlich ersetzt wird.
             */
            $session = $table->tableOrderSessions()
                ->where('active', true)
                ->whereNotNull('token')
                ->latest('id')
                ->first();

            if (! $session) {
                continue;
            }

            $token = $session->token;

            if (! $token) {
                continue;
            }

            $baseUrl = rtrim(
                config('self_order.base_url'),
                '/'
            );

            $url = $baseUrl.'/o/'.$token;

            $qrPng = QrCode::format('png')
                ->size(700)
                ->margin(2)
                ->errorCorrection('M')
                ->generate($url);

            $cards[] = [
                'table' => $table,

                'url' => $url,

                'qrDataUri' =>
                    'data:image/png;base64,'
                    .base64_encode($qrPng),
            ];
        }

        abort_if(
            empty($cards),
            404,
            'Es sind keine verwendbaren Self-Ordering QR-Codes vorhanden.'
        );

        $pdf = Pdf::loadView(
            'admin.tables.qr-pdf-all',
            [
                'cards' => $cards,

                'title' =>
                    Setting::selfOrderingTitle(),

                'subtitle' =>
                    Setting::selfOrderingSubtitle(),
            ]
        );

        $pdf->setPaper(
            'a6',
            'portrait'
        );

        return $pdf->download(
            'wolfcash-self-ordering-tischkarten.pdf'
        );
    }
}
