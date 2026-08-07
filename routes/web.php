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
use App\Livewire\Admin\Orders\Index as OrdersIndex;
use App\Livewire\Admin\Orders\Show as OrderShow;
use App\Livewire\Admin\Cancellations\Index as CancellationsIndex;
use App\Livewire\Admin\DailySummary\Index as DailySummaryIndex;
use App\Livewire\Admin\DailyClosings\Index as DailyClosingsIndex;
use App\Livewire\Admin\DailyClosings\Show as DailyClosingShow;
use App\Livewire\Admin\ProductReports\Index as ProductReportsIndex;
use App\Livewire\Admin\Settings\Index as SettingsIndex;

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


Route::get('/pos', PosIndex::class)
    ->name('pos.index');

Route::get('/pos/checkout/{table}', Checkout::class)
    ->name('pos.checkout');

Route::get('/production', ProductionIndex::class)
    ->name('production.index');


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


            //Bestellübersicht
            Route::get('/orders', OrdersIndex::class)
                ->name('admin.orders');

            Route::get('/orders', OrdersIndex::class)
                ->name('admin.orders');

            Route::get('/orders/{order}', OrderShow::class)
                ->name('admin.orders.show');

            //Stornierungen
            Route::get('/cancellations', CancellationsIndex::class)
                ->name('admin.cancellations');


            //Tagesabrechnung
            Route::get('/daily-summary', DailySummaryIndex::class)
                ->name('admin.daily-summary');

            Route::get(
                '/daily-closings',
                DailyClosingsIndex::class
            )->name('admin.daily-closings');

            Route::get(
                '/daily-closings/{dailyClosing}',
                DailyClosingShow::class
            )->name('admin.daily-closings.show');

            //Verkaufsstatistik
            Route::get(
                '/product-reports',
                ProductReportsIndex::class
            )->name('admin.product-reports');


            //Druckermanagement
            Route::get('/printers', PrintersIndex::class)
                ->name('admin.printers');

            //Arbeitsplätze
            Route::get('/production-stations', ProductionStationsIndex::class)
                ->name('admin.production-stations');

            //Druckjobs
            Route::get('/print-jobs', PrintJobsIndex::class)
                ->name('admin.print-jobs');

            //Einstellungen
            Route::get('/settings',SettingsIndex::class)
                ->name('admin.settings');


    });
});
