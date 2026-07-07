<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\Admin\Devices\Index as DevicesIndex;
use App\Livewire\Pos\Index as PosIndex;
use App\Livewire\Admin\Tables\Index as TablesIndex;
use App\Livewire\Admin\Products\Index as ProductsIndex;
use App\Livewire\Admin\ProductGroups\Index as ProductGroupsIndex;
use App\Livewire\Admin\ProductCategories\Index as ProductCategoriesIndex;
use App\Livewire\Admin\Printers\Index as PrintersIndex;
use App\Livewire\Admin\ProductionStations\Index as ProductionStationsIndex;
use App\Livewire\Admin\PrintJobs\Index as PrintJobsIndex;
use App\Livewire\Production\Index as ProductionIndex;
use App\Livewire\Pos\Checkout;

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

        //Product Management
        Route::get('/products', ProductsIndex::class)
            ->name('admin.products');

        //Produktgruppen und Kategorien
        Route::get('/product-groups',ProductGroupsIndex::class)
            ->name('admin.product-groups');
        Route::get('/product-categories', ProductCategoriesIndex::class)
            ->name('admin.product-categories');

        //Druckermanagement
        Route::get('/printers', PrintersIndex::class)
            ->name('admin.printers');

        //Arbeitsplätze
        Route::get('/production-stations', ProductionStationsIndex::class)
            ->name('admin.production-stations');

        //Druckjobs
        Route::get('/print-jobs', PrintJobsIndex::class)
            ->name('admin.print-jobs');
    });

    Route::get('/pos', PosIndex::class)
        ->name('pos.index');

    Route::get('/pos/checkout/{table}', Checkout::class)
        ->name('pos.checkout');

    Route::get('/production', ProductionIndex::class)
        ->name('production.index');

});
