<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\Devices\Index as DevicesIndex;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome');

/*
|--------------------------------------------------------------------------
| Auth Routes (dein eigenes Livewire Login)
|--------------------------------------------------------------------------
*/

Route::view('/login', 'auth.login')->name('login');

/*
|--------------------------------------------------------------------------
| Protected Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::view('/dashboard', 'dashboard')->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Admin Bereich
    |--------------------------------------------------------------------------
    */

    Route::prefix('admin')->group(function () {

        Route::view('/', 'admin.dashboard')->name('admin.dashboard');

        // Device Management (Livewire)
        Route::get('/devices', DevicesIndex::class)
            ->name('admin.devices');
    });

});
