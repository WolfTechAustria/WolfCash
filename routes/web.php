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
use App\Http\Controllers\MobileWebSessionController;
use App\Livewire\SelfOrder\Index as SelfOrderIndex;
use App\Http\Controllers\SelfOrderPaymentController;
use Illuminate\Http\Request;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\SelfOrder\PaymentStatus;
use App\Http\Controllers\SelfOrderContinueController;
use App\Http\Controllers\Admin\TableQrController;


/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::view('/', 'welcome');

Route::get('/o/{token}',SelfOrderIndex::class)->name('self-order.index');

Route::get('/self-order/{selfOrder}/payment/success',PaymentStatus::class)->name('self-order.payment.success');

Route::get('/self-order/{selfOrder}/payment/cancel',[SelfOrderPaymentController::class,'cancel',])->name('self-order.payment.cancel');

Route::get('/self-order/continue',SelfOrderContinueController::class)->name('self-order.continue');

Route::get('/self-order/resume/{tableSession}',\App\Livewire\SelfOrder\Index::class)
    ->middleware('signed')
    ->name('self-order.resume');

Route::post('/stripe/webhook',[StripeWebhookController::class, 'handle'])->name('stripe.webhook');


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
 * App Route
 */
Route::get(
    '/mobile/session/{code}',
    [MobileWebSessionController::class, 'consume']
)->name('mobile.session.consume');

Route::get(
    '/mobile/ready',
    function (Request $request) {
        abort_unless(
            $request->session()
                ->has('mobile_device_id'),
            403
        );

        return response()->view(
            'mobile.ready',
            [
                'deviceId' =>
                    $request->session()
                        ->get(
                            'mobile_device_id'
                        ),
            ]
        );
    }
);

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


            //PDF QR Code ansicht
            Route::get('/admin/tables/{table}/qr/pdf', [TableQrController::class, 'pdf',])
                ->name('admin.tables.qr.pdf');

            Route::get(
                '/admin/tables/{table}/qr/png',
                [
                    TableQrController::class,
                    'png',
                ]
            )->name(
                'admin.tables.qr.png'
            );

    });
});
