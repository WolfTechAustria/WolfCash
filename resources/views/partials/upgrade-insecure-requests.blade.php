{{--
    Hinter dem HTTPS-Proxy alle Anfragen auf HTTPS heben. Nicht im
    lokalen Netz: dort läuft die Kasse über http://<private IP>
    (Mobile-App mit Autodiscovery) und HTTPS gibt es nicht.
--}}
@unless(\App\Support\LocalNetwork::isLocalRequest())
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
@endunless
