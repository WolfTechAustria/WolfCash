<?php

namespace App\Support;

/**
 * Zugriff über das lokale Netz (Mobile-App mit Autodiscovery,
 * http://<private IPv4>). Dort gibt es kein HTTPS, daher dürfen
 * weder Cookies als Secure markiert noch Anfragen per CSP auf
 * HTTPS umgeschrieben werden.
 */
class LocalNetwork
{
    public static function isPrivateHost(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;
    }

    public static function isLocalRequest(): bool
    {
        return self::isPrivateHost(request()->getHost());
    }
}
