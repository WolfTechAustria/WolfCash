@props([
    'line',
])

{{--
    Vergrößerte Bonzeile wie am Drucker: Höhe über die Schriftgröße,
    Breite über scaleX. Der innere Block ist um Höhe/Breite breiter,
    damit genau so viele Zeichen pro Zeile umbrechen wie am Papier
    (z. B. 16 bei doppelter Breite).
--}}
@php
    $width = $line->size->width();
    $height = $line->size->height();
@endphp

<div style="overflow-x: clip;">
    <div
        @class([
            'whitespace-pre-wrap break-words',
            'font-bold' => $line->bold,
            'text-center' => $line->center,
        ])
        style="font-size: {{ $height }}em; width: {{ $height / $width * 100 }}%; transform: scaleX({{ $width / $height }}); transform-origin: left;"
    >{{ $line->text }}</div>
</div>
