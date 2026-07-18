<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Drucktreiber
    |--------------------------------------------------------------------------
    |
    | simulation:
    |   Es werden keine Daten an einen physischen Drucker gesendet.
    |
    | escpos_network:
    |   Die Ausgabe wird über TCP/IP als ESC/POS-Daten gesendet.
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
