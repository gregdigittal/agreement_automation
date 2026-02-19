<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new \App\Jobs\SendReminders)->hourly();
Schedule::job(new \App\Jobs\CheckSlaBreaches)->everyFifteenMinutes();
Schedule::job(new \App\Jobs\SendPendingNotifications)->everyFiveMinutes();
