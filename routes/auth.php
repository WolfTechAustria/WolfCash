<?php

use Illuminate\Support\Facades\Route;

Route::view('/login', 'auth.login')->name('login');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
});

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
});
