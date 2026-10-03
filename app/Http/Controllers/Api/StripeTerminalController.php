<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Stripe\StripeClient;

class StripeTerminalController extends Controller
{
    public function connectionToken(): JsonResponse
    {
        $stripe = new StripeClient(
            config('services.stripe.secret')
        );

        $token = $stripe
            ->terminal
            ->connectionTokens
            ->create([]);

        return response()->json([
            'secret' => $token->secret,
        ]);
    }

    /**
     * Einstellungen, die die App zum Verbinden des Tap-to-Pay-
     * Readers braucht.
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'location_id' => config('services.stripe.terminal_location_id'),
        ]);
    }
}
