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
    | Drucker für Kundenbelege
    |--------------------------------------------------------------------------
    |
    | ID des Druckers aus der Tabelle printers.
    |
    */

    'receipt_printer_id' => env(
        'PRINT_RECEIPT_PRINTER_ID',
        0
    ),


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
