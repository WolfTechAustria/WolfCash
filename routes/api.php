<?php

use App\Http\Controllers\Api\DeviceController;
use Illuminate\Support\Facades\Route;

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
