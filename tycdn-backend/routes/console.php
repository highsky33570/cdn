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

Schedule::command('cdnfly:rotate-keys --days=90')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping();
