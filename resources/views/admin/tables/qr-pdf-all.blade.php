<!doctype html>
<html lang="de">

<head>

    <meta charset="UTF-8">

    <style>

        @page {
            margin: 0;
        }

        html,
        body {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            background: #ffffff;
            color: #111111;
        }

        .page {
            width: 100%;
            height: 100%;
            text-align: center;
            page-break-after: always;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .card {
            padding: 12mm 10mm 8mm 10mm;
            text-align: center;
        }

        .brand {
            font-size: 10pt;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 8mm;
        }

        .title {
            font-size: 20pt;
            line-height: 1.15;
            font-weight: bold;
            margin: 0 0 4mm 0;
        }

        .table-number {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 7mm;
        }

        .qr {
            width: 65mm;
            height: 65mm;
            margin: 0 auto;
        }

        .qr img {
            width: 65mm;
            height: 65mm;
        }

        .subtitle {
            font-size: 11pt;
            font-weight: bold;
            margin-top: 7mm;
        }

        .hint {
            font-size: 8pt;
            line-height: 1.4;
            color: #666666;
            margin-top: 3mm;
        }

        .url {
            margin-top: 4mm;
            font-size: 5.5pt;
            color: #999999;
            word-wrap: break-word;
        }

    </style>

</head>


<body>

@foreach($cards as $card)

    <div class="page">

        <div class="card">


            <div class="title">
                {{ $title }}
            </div>

            <div class="subtitle">
                {{ $subtitle }}
            </div>



            <div class="table-number">
                Tisch {{ $card['table']->number }}
            </div>


            <div class="qr">

                <img
                    src="{{ $card['qrDataUri'] }}"
                    alt="QR-Code"
                >

            </div>




            <div class="hint">
                QR-Code mit dem Smartphone scannen,
                Bestellung auswählen und direkt bezahlen.
            </div>


            <div class="url">
                {{ $card['url'] }}
            </div>

        </div>

    </div>

@endforeach

</body>

</html>
