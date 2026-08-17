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
}
