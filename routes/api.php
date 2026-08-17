<?php

use App\Http\Controllers\Api\DeviceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MobileSessionController;
use App\Http\Controllers\Api\StripeTerminalController;


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



Route::middleware('device.auth')->group(function (): void {
    Route::post(
        '/stripe/terminal/connection-token',
        [
            StripeTerminalController::class,
            'connectionToken',
        ]
    );

    Route::post(
        '/mobile/session',
        [
            MobileSessionController::class,
            'store',
        ]
    );
});


