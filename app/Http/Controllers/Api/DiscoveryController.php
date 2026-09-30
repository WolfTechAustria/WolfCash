<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ServerIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoveryController extends Controller
{
    /**
     * Beantwortet die Challenge der Mobile-App mit einer
     * Signatur über server_id und Nonce.
     */
    public function identify(
        Request $request,
        ServerIdentity $identity
    ): JsonResponse {
        $validated = $request->validate([
            'nonce' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9_-]{16,128}$/',
            ],
        ]);

        if (! $identity->isConfigured()) {
            return response()->json([
                'message' => 'Server-Identität ist nicht eingerichtet.',
            ], 503);
        }

        return response()->json([
            'server_id' => $identity->serverId(),
            'name' => $identity->name(),
            'public_key' => $identity->publicKey(),
            'signature' => $identity->sign(
                $validated['nonce']
            ),
        ]);
    }
}
