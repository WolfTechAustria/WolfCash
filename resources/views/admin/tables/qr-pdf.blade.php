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
            width: 100%;
            height: 100%;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            background: #ffffff;
            color: #111111;
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

        .table {
            font-size: 13pt;
            font-weight: bold;
            margin-bottom: 7mm;
        }

        .qr {
            width: 75mm;
            height: 75mm;
            margin: 0 auto;
        }

        .qr img {
            width: 75mm;
            height: 75mm;
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

<div class="card">

    <div class="brand">
        WolfCash
    </div>

    <div class="title">
        {{ $title }}
    </div>

    <div class="table">
        Tisch {{ $table->number }}
    </div>

    <div class="qr">
        <img
            src="{{ $qrDataUri }}"
            alt="QR-Code"
        >
    </div>

    <div class="subtitle">
        {{ $subtitle }}
    </div>

    <div class="hint">
        QR-Code mit dem Smartphone scannen,
        Bestellung auswählen und direkt bezahlen.
    </div>

    <div class="url">
        {{ $url }}
    </div>

</div>

</body>
</html>
