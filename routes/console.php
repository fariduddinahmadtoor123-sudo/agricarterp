<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if (config('ai.schedule_enabled', false)) {
    Schedule::command('catalog:enrich-pending')
        ->hourly()
        ->withoutOverlapping()
        ->onOneServer();
}

if (config('backup.schedule_enabled', true)) {
    Schedule::command('backup:run-scheduled')
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
}

