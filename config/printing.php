<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Drucktreiber
    |--------------------------------------------------------------------------
    |
    | simulation:
    |   Es werden keine Daten an einen Drucker gesendet.
    |
    | escpos:
    |   Die Ausgabe wird über ESC/POS an den Drucker gesendet.
    |
    */

    'driver' => env('PRINT_DRIVER', 'simulation'),

    /*
    |--------------------------------------------------------------------------
    | Netzwerk-Timeout
    |--------------------------------------------------------------------------
    */

    'network_timeout' => (int) env(
        'PRINT_NETWORK_TIMEOUT',
        5
    ),
];
