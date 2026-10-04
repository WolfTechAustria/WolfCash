<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileSessionCode;
use App\Support\LocalNetwork;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $device = $request->attributes->get('device');

        if (! $device) {
            return response()->json([
                'message' => 'Device not authenticated.',
            ], 401);
        }

        // Noch nicht verwendete alte Codes dieses Gerätes ungültig machen.
        MobileSessionCode::query()
            ->where('device_id', $device->id)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
            ]);

        $sessionCode = MobileSessionCode::create([
            'device_id' => $device->id,
            'code' => Str::random(64),
            'expires_at' => now()->addMinutes(2),
        ]);

        return response()->json([
            'url' => $this->webBaseUrl($request)
                .'/mobile/session/'
                .$sessionCode->code,
        ]);
    }

    /**
     * Geräte, die den Server über das lokale Netz erreichen
     * (Autodiscovery), sollen auch die Kasse lokal laden.
     */
    private function webBaseUrl(Request $request): string
    {
        if (LocalNetwork::isPrivateHost($request->getHost())) {
            return $request->getSchemeAndHttpHost();
        }

        return rtrim(
            config('app.mobile_web_url'),
            '/'
        );
    }
}
