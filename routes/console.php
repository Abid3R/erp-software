<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily operational alerts (low stock, overdue receivables) per company.
// Requires the OS scheduler to run `php artisan schedule:run` every minute;
// the alerts can also be triggered on demand from Notification Settings.
Schedule::command('erp:notifications:send')->dailyAt('08:00')->withoutOverlapping();
