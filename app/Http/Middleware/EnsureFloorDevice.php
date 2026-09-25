<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Geräteprüfung für die Kellner-/Kassen-/Produktionsoberflächen.
 *
 * Zugelassen sind:
 * - angemeldete Benutzer (Admins, z. B. "Kasse öffnen"),
 * - die Mobile-App über ihre Session (mobile_device_id),
 * - Browser, deren Geräte-Cookie zu einem freigegebenen Gerät gehört.
 *
 * Alle anderen sehen die Freigabe-Seite. Als persistente
 * Livewire-Middleware greift die Prüfung auch bei jedem Klick.
 */
class EnsureFloorDevice
{
    /*
     * Wird per JavaScript aus localStorage gesetzt,
     * daher unverschlüsselt (siehe bootstrap/app.php).
     */
    public const COOKIE = 'wolfcash_device';

    /*
     * 423 Locked: eindeutiges Signal an das Layout-Skript,
     * dass die Geräteprüfung einen Livewire-Klick abgewiesen hat.
     */
    public const LIVEWIRE_STATUS = 423;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            return $next($request);
        }

        $device = $this->resolveDevice($request);

        if ($device?->status === DeviceStatus::Approved) {
            $device->forceFill(['last_seen_at' => now()])->saveQuietly();

            return $next($request);
        }

        $status = $device?->status->value ?? 'unknown';

        /*
         * Livewire-Klick: Livewire verwirft Antworten persistenter
         * Middleware (außer Redirects), daher werfen. 423 erkennt das
         * Layout und lädt neu, wodurch die Freigabe-Seite erscheint.
         */
        if ($request->hasHeader('X-Livewire')) {
            throw new HttpResponseException(response()->json([
                'message' => 'Gerät ist nicht freigegeben.',
                'status' => $status,
            ], self::LIVEWIRE_STATUS));
        }

        return response()->view('device.gate', [
            'status' => $status,

            /*
             * Die App verwaltet ihr Gerät selbst; die Seite darf dann
             * keinen zusätzlichen Browser registrieren.
             */
            'viaApp' => $request->session()->has('mobile_device_id'),
        ], 403);
    }

    private function resolveDevice(Request $request): ?Device
    {
        /*
         * Mobile-App: freigegebenes Gerät wurde beim Session-Aufbau
         * geprüft, der Status wird hier trotzdem jedes Mal neu gelesen,
         * damit eine spätere Sperre sofort greift.
         */
        $mobileDeviceId = $request->session()->get('mobile_device_id');

        if ($mobileDeviceId) {
            return Device::find($mobileDeviceId);
        }

        $fingerprint = (string) $request->cookie(self::COOKIE, '');

        if ($fingerprint === '' || strlen($fingerprint) > 255) {
            return null;
        }

        return Device::query()
            ->where('fingerprint', $fingerprint)
            ->first();
    }
}
