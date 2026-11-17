@php
    $eur = fn ($value) => number_format((float) $value, 2, ',', '.').' €';
@endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Tagesübersicht {{ $selectedDate->format('d.m.Y') }}</title>
    <style>
        @page {
            margin: 18mm 15mm 16mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #1b1a17;
        }

        h1 {
            margin: 0;
            font-size: 17pt;
        }

        h2 {
            margin: 18px 0 6px;
            font-size: 11.5pt;
        }

        .muted {
            color: #6b675e;
        }

        .filter {
            margin-top: 8px;
            padding: 6px 8px;
            border: 1px solid #d9a441;
            background: #fbf3e2;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 4px 6px;
            border-bottom: 1px solid #e3e0d8;
            text-align: left;
            vertical-align: top;
        }

        th {
            font-size: 8pt;
            text-transform: uppercase;
            color: #6b675e;
            border-bottom: 1px solid #b9b5ab;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .kpis td {
            width: 25%;
            padding: 6px 8px;
            border: 1px solid #e3e0d8;
        }

        .kpis .label {
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #6b675e;
        }

        .kpis .value {
            margin-top: 2px;
            font-size: 12pt;
            font-weight: bold;
        }

        .footer {
            margin-top: 18px;
            font-size: 7.5pt;
        }
    </style>
</head>
<body>

<h1>Tagesübersicht</h1>
<div class="muted">
    {{ $selectedDate->locale('de')->translatedFormat('l, d. F Y') }}
    @if($dailyClosing)
        · Tag abgeschlossen am {{ $dailyClosing->closed_at->format('d.m.Y H:i') }}
    @endif
</div>

@if($deviceLabel !== null)
    <div class="filter">
        Gerät: <strong>{{ $deviceLabel }}</strong> –
        Zahlungen = mit diesem Gerät kassiert, Umsatz und Stornos = mit diesem Gerät boniert.
    </div>
@endif

<h2>Kennzahlen</h2>
<table class="kpis">
    <tr>
        <td><div class="label">Verrechenbarer Umsatz</div><div class="value">{{ $eur($summary['payable_amount']) }}</div></td>
        <td><div class="label">Bezahlt</div><div class="value">{{ $eur($summary['paid_amount']) }}</div></td>
        <td><div class="label">Noch offen</div><div class="value">{{ $eur($summary['open_amount']) }}</div></td>
        <td><div class="label">Storniert</div><div class="value">{{ $eur($summary['cancelled_amount']) }}</div></td>
    </tr>
    <tr>
        <td><div class="label">Bar</div><div class="value">{{ $eur($summary['cash_amount']) }}</div></td>
        <td><div class="label">Karte</div><div class="value">{{ $eur($summary['card_amount']) }}</div></td>
        <td><div class="label">Bons eingelöst (nicht im Umsatz)</div><div class="value">{{ $eur($summary['voucher_amount']) }}</div></td>
        <td><div class="label">Ursprünglicher Wert</div><div class="value">{{ $eur($summary['gross_amount']) }}</div></td>
    </tr>
</table>

<table style="margin-top: 6px;">
    <tr>
        <td>Bestellungen: <strong>{{ $summary['orders_total'] }}</strong></td>
        <td>bezahlt: {{ $summary['orders_paid'] }}</td>
        <td>offen: {{ $summary['orders_open'] }}</td>
        <td>Vollstornos: {{ $summary['orders_cancelled'] }}</td>
        <td>Zahlungen: {{ $summary['payments_count'] }}</td>
        <td>Stornovorgänge: {{ $summary['cancellations_count'] }} ({{ $summary['cancelled_quantity'] }} Stk.)</td>
    </tr>
</table>

@if($deviceLabel === null && $devices->isNotEmpty())
    <h2>Nach Gerät</h2>
    <table>
        <thead>
        <tr>
            <th>Gerät</th>
            <th class="num">Boniert</th>
            <th class="num">Kassiert</th>
            <th class="num">Bar</th>
            <th class="num">Karte</th>
            <th class="num">Bons</th>
            <th class="num">Zahlungen</th>
        </tr>
        </thead>
        <tbody>
        @foreach($devices as $deviceRow)
            <tr>
                <td>{{ $deviceRow['name'] }}</td>
                <td class="num">{{ $eur($deviceRow['booked_amount']) }}</td>
                <td class="num"><strong>{{ $eur($deviceRow['paid_amount']) }}</strong></td>
                <td class="num">{{ $eur($deviceRow['cash_amount']) }}</td>
                <td class="num">{{ $eur($deviceRow['card_amount']) }}</td>
                <td class="num">{{ $eur($deviceRow['voucher_amount']) }}</td>
                <td class="num">{{ $deviceRow['payments_count'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if($openOrders->isNotEmpty())
    <h2>Offene Bestellungen ({{ $openOrders->count() }})</h2>
    <table>
        <thead>
        <tr>
            <th>Bestellung</th>
            <th>Tisch</th>
            <th>Zeit</th>
            <th class="num">Offen</th>
        </tr>
        </thead>
        <tbody>
        @foreach($openOrders as $order)
            <tr>
                <td>#{{ $order->id }}</td>
                <td>{{ $order->table?->number ?? '–' }}</td>
                <td>{{ $order->created_at->format('H:i') }}</td>
                <td class="num">{{ $eur($order->items->whereNull('paid_at')->sum(fn ($item) => (float) $item->price * max(0, (int) $item->quantity - (int) $item->cancelled_quantity))) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<h2>Zahlungen ({{ $payments->count() }})</h2>
@if($payments->isEmpty())
    <p class="muted">Keine Zahlungen an diesem Tag.</p>
@else
    <table>
        <thead>
        <tr>
            <th>Zeit</th>
            <th>Bestellung</th>
            <th>Tisch</th>
            <th>Zahlungsart</th>
            <th>Gerät</th>
            <th class="num">Betrag</th>
        </tr>
        </thead>
        <tbody>
        @foreach($payments->sortBy('created_at') as $payment)
            <tr>
                <td>{{ $payment->created_at->format('H:i') }}</td>
                <td>#{{ $payment->order_id }}</td>
                <td>{{ $payment->order?->table?->number ?? '–' }}</td>
                <td>{{ \App\Models\Payment::methodLabel($payment->payment_method) }}</td>
                <td>{{ $payment->device?->name ?? '–' }}</td>
                <td class="num">{{ $eur($payment->amount) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<div class="footer muted">
    Erstellt am {{ $generatedAt->format('d.m.Y H:i') }}
    @if($dailyClosing)
        · Live-Werte aus den aktuellen Daten, nicht aus dem gespeicherten Tagesabschluss.
    @endif
</div>

</body>
</html>
