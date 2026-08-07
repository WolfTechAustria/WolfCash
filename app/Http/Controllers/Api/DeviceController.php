<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function register(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'uuid' => [
                'required',
                'uuid',
            ],

            'fingerprint' => [
                'required',
                'string',
                'max:255',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'platform' => [
                'nullable',
                'string',
                'max:50',
            ],

            'app_version' => [
                'nullable',
                'string',
                'max:50',
            ],
        ]);

        $device = Device::query()
            ->firstOrCreate(
                [
                    'fingerprint' =>
                        $validated['fingerprint'],
                ],
                [
                    'uuid' =>
                        $validated['uuid'],

                    'name' =>
                        $validated['name'],

                    'platform' =>
                        $validated['platform'] ?? null,

                    'app_version' =>
                        $validated['app_version'] ?? null,

                    'status' =>
                        DeviceStatus::Pending,
                ]
            );

        /*
         * Gerätedaten bei erneutem Start aktualisieren.
         */
        $device->update([
            'name' => $validated['name'],

            'platform' =>
                $validated['platform'] ?? null,

            'app_version' =>
                $validated['app_version'] ?? null,

            'last_seen_at' => now(),
        ]);

        return response()->json([
            'device_id' => $device->id,
            'status' => $device->status->value,
        ]);
    }

    public function status(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'fingerprint' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $device = Device::query()
            ->where(
                'fingerprint',
                $validated['fingerprint']
            )
            ->first();

        if (! $device) {
            return response()->json([
                'status' => 'unknown',
            ]);
        }

        $device->update([
            'last_seen_at' => now(),
        ]);

        $response = [
            'device_id' => $device->id,
            'status' => $device->status->value,
        ];

        /*
         * Erst nach Freigabe bekommt die App den Token.
         */
        if (
            $device->status === DeviceStatus::Approved
            && $device->api_token
        ) {
            $response['token'] =
                $device->api_token;
        }

        return response()->json(
            $response
        );
    }
}
