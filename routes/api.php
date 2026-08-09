<?php

use App\Http\Controllers\Api\DeviceController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MobileSessionController;



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

    Route::middleware('device.auth')->post(
        '/mobile/session',
        [MobileSessionController::class, 'store']
    );


