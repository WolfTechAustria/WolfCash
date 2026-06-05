<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\Devices\Index as DevicesIndex;
use App\Livewire\Pos\Index as PosIndex;
use App\Livewire\Admin\Tables\Index as TablesIndex;

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

        //Tables Management
        Route::get('/tables', TablesIndex::class)
            ->name('admin.tables');
    });

    Route::get('/pos', PosIndex::class)
        ->name('pos.index');

});
