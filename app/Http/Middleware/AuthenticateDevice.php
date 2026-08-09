<?php

namespace App\Http\Middleware;

use App\Enums\DeviceStatus;
use App\Models\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'message' => 'Device token missing.',
            ], 401);
        }

        /*
         * Da api_token aktuell verschlüsselt gespeichert
         * wird, können wir nicht direkt per SQL danach suchen.
         *
         * Für den derzeit kleinen Gerätebestand ist das
         * zunächst ausreichend.
         */
        $device = Device::query()
            ->get()
            ->first(
                fn (Device $device) =>
                    $device->api_token
                    && hash_equals(
                        $device->api_token,
                        $token
                    )
            );

        if (! $device) {
            return response()->json([
                'message' => 'Invalid device token.',
            ], 401);
        }

        if (
            $device->status
            !== DeviceStatus::Approved
        ) {
            return response()->json([
                'message' => 'Device is not approved.',
            ], 403);
        }

        $device->update([
            'last_seen_at' => now(),
        ]);

        $request->attributes->set(
            'device',
            $device
        );

        return $next($request);
    }
}
