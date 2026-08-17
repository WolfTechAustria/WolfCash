<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;


use App\Models\MobileSessionCode;

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
