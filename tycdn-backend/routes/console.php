<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('payments:reconcile')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Prepaid renewal: once a day, extend packages near expiry by charging the
// customer's CDNfly balance. Daily is enough — the window is measured in days.
Schedule::command('cdnfly:auto-renew')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('cdnfly:rotate-keys --days=90')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping();
