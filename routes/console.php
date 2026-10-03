<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


use App\Models\MobileSessionCode;
use App\Services\CardPaymentService;
use App\Services\StockService;

Schedule::call(function (): void {
    MobileSessionCode::query()
        ->where('created_at', '<', now()->subDay())
        ->delete();
})->daily();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('print:process')
    ->everyFiveSeconds();

/*
 * Verfallene Warenkorb-Reservierungen freigeben
 * (15 Min. Inaktivität, abgebrochene Self-Order-Zahlungen).
 */
Schedule::call(fn () => app(StockService::class)->purgeExpired())
    ->name('stock:purge-expired-reservations')
    ->everyMinute()
    ->withoutOverlapping();

/*
 * Nie abgeschlossene Kartenzahlungen (App beendet, WebView neu
 * geladen, …) bei Stripe stornieren, damit die Reservierung auf
 * der Kundenkarte freigegeben wird.
 */
Schedule::call(fn () => app(CardPaymentService::class)->expireStale())
    ->name('card-payments:expire-stale')
    ->everyFiveMinutes()
    ->withoutOverlapping();
