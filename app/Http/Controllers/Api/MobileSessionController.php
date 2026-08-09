<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\MobileSessionCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileSessionController extends Controller
{
    public function store(
        Request $request
    ): JsonResponse {
        /** @var Device $device */
        $device = $request->attributes->get(
            'device'
        );

        /*
         * Alte, noch offene Codes dieses Geräts
         * ungültig machen.
         */
        MobileSessionCode::query()
            ->where('device_id', $device->id)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
            ]);

        $sessionCode =
            MobileSessionCode::create([
                'device_id' => $device->id,
                'code' => Str::random(64),
                'expires_at' => now()
                    ->addMinutes(2),
            ]);

        return response()->json([
            'url' => route(
                'mobile.session.consume',
                [
                    'code' =>
                        $sessionCode->code,
                ]
            ),
        ]);
    }
}
