<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('production:generate')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/production-generate.log'));

Schedule::command('pulses:generate')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/pulses-generate.log'));
    
Schedule::command('vacation:rebuild')->yearlyOn(1, 1, '03:00');

Schedule::command('attendance:auto-checkout')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/attendance-auto-checkout.log'));

Schedule::command('digest:daily')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/daily-digest.log'));