<?php

namespace App\Http\Middleware;

use App\Support\LocalNetwork;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bei Zugriff über das lokale Netz (http://<private IPv4>) darf das
 * Session-Cookie nicht Secure sein, sonst verwirft der Browser es und
 * die Mobile-App landet nach dem Login auf der Freigabe-Seite.
 *
 * Muss vor StartSession laufen (siehe bootstrap/app.php).
 */
class AllowLocalNetworkHttp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (LocalNetwork::isPrivateHost($request->getHost())) {
            config(['session.secure' => false]);
        }

        return $next($request);
    }
}
