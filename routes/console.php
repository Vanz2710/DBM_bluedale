<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fire due email campaigns every minute (requires `php artisan schedule:work` or a cron entry).
Schedule::command('email:dispatch-scheduled')->everyMinute()->withoutOverlapping();
