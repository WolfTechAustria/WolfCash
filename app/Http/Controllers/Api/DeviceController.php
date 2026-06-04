<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use App\Enums\DeviceStatus;

class DeviceController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'fingerprint' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        $device = Device::firstOrCreate(
            [
                'uuid' => $validated['uuid'],
            ],
            [
                'name' => $validated['name'],
                'fingerprint' => $validated['fingerprint'],
                'platform' => $validated['platform'] ?? null,
                'app_version' => $validated['app_version'] ?? null,
                'status' => DeviceStatus::Pending,
            ]
        );

        return response()->json([
            'status' => $device->status->value,
        ]);
    }
}
