<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\Device;

/*
|--------------------------------------------------------------------------
| Device Register
|--------------------------------------------------------------------------
*/

Route::post('/device/register', function (Request $request) {

    $request->validate([
        'fingerprint' => 'required|string',
        'platform' => 'nullable|string',
    ]);

    $device = Device::firstOrCreate(
        ['fingerprint' => $request->fingerprint],
        [
            'name' => $request->name ?? 'Unknown Device',
            'platform' => $request->platform ?? 'browser',
            'status' => 'pending',
        ]
    );

    return response()->json([
        'device_id' => $device->id,
        'status' => $device->status,
    ]);
});

Route::post('/device/status', function (Request $request) {

    $request->validate([
        'fingerprint' => 'required|string',
    ]);

    $device = Device::where('fingerprint', $request->fingerprint)->first();

    if (! $device) {
        return response()->json([
            'status' => 'unknown'
        ]);
    }

    return response()->json([
        'status' => $device->status,
        'device_id' => $device->id,
    ]);
});

