<?php

use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DiscoveryController;
use App\Http\Controllers\Api\MobileSessionController;
use App\Http\Controllers\Api\StripeTerminalController;
use Illuminate\Support\Facades\Route;


/*
|--------------------------------------------------------------------------
| Server Discovery
|--------------------------------------------------------------------------
*/

Route::post(
    '/discovery/identify',
    [DiscoveryController::class, 'identify']
)->middleware('throttle:30,1');


/*
|--------------------------------------------------------------------------
| Device Registration
|--------------------------------------------------------------------------
*/

Route::prefix('device')->group(function (): void {

    Route::post(
        '/register',
        [DeviceController::class, 'register']
    );

    Route::post(
        '/status',
        [DeviceController::class, 'status']
    );

});


/*
|--------------------------------------------------------------------------
| Authenticated Mobile Device API
|--------------------------------------------------------------------------
*/

Route::middleware('device.auth')
    ->group(function (): void {

        Route::post(
            '/mobile/session',
            [MobileSessionController::class, 'store']
        );

        Route::post(
            '/stripe/terminal/connection-token',
            [
                StripeTerminalController::class,
                'connectionToken',
            ]
        );

    });
