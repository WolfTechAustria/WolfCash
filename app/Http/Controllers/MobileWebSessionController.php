<?php

namespace App\Http\Controllers;

use App\Models\MobileSessionCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MobileWebSessionController extends Controller
{
    public function consume(
        Request $request,
        string $code
    ): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
    {
        $sessionCode =
            MobileSessionCode::query()
                ->with('device')
                ->where('code', $code)
                ->firstOrFail();

        abort_unless(
            $sessionCode->isUsable(),
            403,
            'Session code expired or already used.'
        );

        $request->session()->regenerate();

        $request->session()->put(
            'mobile_device_id',
            $sessionCode->device_id
        );

        $request->session()->put(
            'mobile_device_uuid',
            $sessionCode->device->uuid
        );

        $sessionCode->update([
            'used_at' => now(),
        ]);

        return response('', 302)
            ->header('Location', '/mobile/ready');
    }
}
