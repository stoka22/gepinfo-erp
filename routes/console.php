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

// A háttérbe tett jobokat (pl. GenerateAttendanceSheetBatchJob, Filament adatbázis-
// értesítések) ez dolgozza fel — megosztott/DotRoll-os környezetben nincs tartósan futó
// 'queue:work' daemon, ezért percenként egy rövid, önmagát leállító burst-öt indítunk a
// már meglévő schedule:run cronra ráülve (ugyanaz a minta, mint a többi Schedule::command
// itt fent). A --stop-when-empty azonnal kilép, ha nincs teendő; a --max-time=50 garantálja,
// hogy a következő perces indítás előtt biztosan befejeződik.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/queue-work.log'));

Schedule::command('attendance:auto-checkout')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/attendance-auto-checkout.log'));

Schedule::command('digest:daily')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/daily-digest.log'));