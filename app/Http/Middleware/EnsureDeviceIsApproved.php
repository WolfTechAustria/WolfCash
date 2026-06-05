<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Device;

class EnsureDeviceIsApproved
{
    public function handle(Request $request, Closure $next)
    {
        $deviceId = $request->header('X-DEVICE-ID');

        if (!$deviceId) {
            return response()->json([
                'message' => 'Device ID missing'
            ], 403);
        }

        $device = Device::find($deviceId);

        if (!$device || $device->status !== 'approved') {
            return response()->json([
                'message' => 'Device not approved'
            ], 403);
        }

        return $next($request);
    }
}
