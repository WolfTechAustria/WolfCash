<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DeviceController;

Route::post('/device/register', [
    DeviceController::class,
    'register'
]);
