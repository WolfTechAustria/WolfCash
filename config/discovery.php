<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Server-Identität für die Autodiscovery der Mobile-App
    |--------------------------------------------------------------------------
    |
    | Die App merkt sich beim ersten Kontakt über die öffentliche HTTPS-URL
    | server_id und öffentlichen Schlüssel dieser Installation. Im lokalen
    | Netz (mDNS) gefundene Server verwendet sie nur, wenn sie eine
    | Challenge mit dem passenden privaten Schlüssel signieren.
    |
    | Erzeugen mit: php artisan wolfcash:server-identity
    |
    */

    'server_id' => env('WOLFCASH_SERVER_ID'),

    'server_name' => env('WOLFCASH_SERVER_NAME', env('APP_NAME', 'WolfCash')),

    /*
     * Ed25519 Secret Key (64 Byte, base64).
     */
    'secret_key' => env('WOLFCASH_SERVER_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | mDNS / Avahi
    |--------------------------------------------------------------------------
    |
    | Dienst-Typ und Werte für die Avahi-Service-Datei, die
    | wolfcash:server-identity --avahi ausgibt.
    |
    */

    'mdns_service_type' => '_wolfcash._tcp',

    'mdns_port' => (int) env('WOLFCASH_MDNS_PORT', 80),

    'mdns_scheme' => env('WOLFCASH_MDNS_SCHEME', 'http'),
];
