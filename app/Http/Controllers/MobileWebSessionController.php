<?php

namespace App\Http\Controllers;

use App\Models\MobileSessionCode;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MobileWebSessionController extends Controller
{
    public function consume(
        Request $request,
        string $code
    ): Response {
        $sessionCode = MobileSessionCode::query()
            ->with('device')
            ->where('code', $code)
            ->firstOrFail();

        abort_unless(
            $sessionCode->isUsable(),
            403,
            'Session code expired or already used.'
        );

        $device = $sessionCode->device;

        abort_unless(
            $device !== null,
            403,
            'Device not found.'
        );

        abort_unless(
            $device->status->value === 'approved',
            403,
            'Device not approved.'
        );

        $request->session()->regenerate();

        $request->session()->put(
            'mobile_device_id',
            $device->id
        );

        $request->session()->put(
            'mobile_device_uuid',
            $device->uuid
        );

        // Erst nach erfolgreicher Session-Erzeugung verbrauchen.
        $sessionCode->update([
            'used_at' => now(),
        ]);

        return response('', 302)
            ->header('Location', '/pos');
    }
}
