@php
    $eur = fn ($value) => number_format((float) $value, 2, ',', '.').' €';
    $range = $selectedFrom->isSameDay($selectedTo)
        ? $selectedFrom->format('d.m.Y')
        : $selectedFrom->format('d.m.Y').' – '.$selectedTo->format('d.m.Y');
@endphp
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Produktauswertung {{ $range }}</title>
    <style>
        @page {
            margin: 18mm 15mm 16mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
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
            font-size: 7.5pt;
            text-transform: uppercase;
            color: #6b675e;
            border-bottom: 1px solid #b9b5ab;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        .num {
            text-align: right;
            white-space: nowrap;
        }

        .total td {
            font-weight: bold;
            border-top: 1px solid #b9b5ab;
            border-bottom: none;
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

<h1>Produktauswertung</h1>
<div class="muted">{{ $range }}</div>

@if($filters !== [])
    <div class="filter">
        Gefiltert nach
        @foreach($filters as $label => $value)
            {{ $label }}: <strong>{{ $value }}</strong>@if(! $loop->last), @endif
        @endforeach
    </div>
@endif

<h2>Kennzahlen</h2>
<table class="kpis">
    <tr>
        <td><div class="label">Verkaufte Produkte</div><div class="value">{{ $summary['products'] }}</div></td>
        <td>
            <div class="label">Verkaufte Stück</div>
            <div class="value">{{ $summary['quantity'] }}</div>
            <div class="muted">{{ $summary['ordered_quantity'] }} ursprünglich bestellt</div>
        </td>
        <td><div class="label">Verrechenbarer Umsatz</div><div class="value">{{ $eur($summary['revenue']) }}</div></td>
        <td>
            <div class="label">Storniert</div>
            <div class="value">{{ $eur($summary['cancelled_amount']) }}</div>
            <div class="muted">{{ $summary['cancelled_quantity'] }} Stück</div>
        </td>
    </tr>
</table>

<h2>Produkte ({{ $rows->count() }})</h2>
@if($rows->isEmpty())
    <p class="muted">Für den gewählten Zeitraum wurden keine Produktverkäufe gefunden.</p>
@else
    <table>
        <thead>
        <tr>
            <th>Produkt</th>
            <th>Gruppe · Kategorie</th>
            <th class="num">Bestellt</th>
            <th class="num">Storniert</th>
            <th class="num">Verkauft</th>
            <th class="num">Ø Preis</th>
            <th class="num">Storno €</th>
            <th class="num">Umsatz</th>
        </tr>
        </thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td class="muted">{{ $row['group'] }} · {{ $row['category'] }}</td>
                <td class="num">{{ $row['ordered_quantity'] }}</td>
                <td class="num">{{ $row['cancelled_quantity'] }}</td>
                <td class="num">{{ $row['quantity'] }}</td>
                <td class="num">{{ $eur($row['average_price']) }}</td>
                <td class="num">{{ $eur($row['cancelled_amount']) }}</td>
                <td class="num"><strong>{{ $eur($row['revenue']) }}</strong></td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="2">Summe</td>
            <td class="num">{{ $summary['ordered_quantity'] }}</td>
            <td class="num">{{ $summary['cancelled_quantity'] }}</td>
            <td class="num">{{ $summary['quantity'] }}</td>
            <td></td>
            <td class="num">{{ $eur($summary['cancelled_amount']) }}</td>
            <td class="num">{{ $eur($summary['revenue']) }}</td>
        </tr>
        </tbody>
    </table>
@endif

<div class="footer muted">
    Erstellt am {{ $generatedAt->format('d.m.Y H:i') }}
    · Per Bon eingelöste Positionen sind nicht enthalten (zählen beim Bon-Verkauf).
</div>

</body>
</html>
