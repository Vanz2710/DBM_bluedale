<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('workflow:inactivity-check')->dailyAt('08:00');
Schedule::command('dept:notify-overdue')->dailyAt('09:00');
Schedule::command('dept:prune-notifications')->dailyAt('02:00');
Schedule::command('email:dispatch-scheduled')->everyMinute();
Schedule::command('users:purge-deleted')->dailyAt('03:00');
// Delete personal access tokens that have been expired for 24h+ so the
// personal_access_tokens table doesn't accumulate dead rows over time.
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('04:00');
