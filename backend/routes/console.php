<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('auctions:finalize-expired')->everyMinute()->withoutOverlapping();
if (config('release.demo_mode')) {
    Schedule::command('releases:activate-scheduled')->everySecond()->withoutOverlapping();
    Schedule::command('releases:dispatch-due-events')->everySecond()->withoutOverlapping();
} else {
    Schedule::command('releases:activate-scheduled')->everyMinute()->withoutOverlapping();
    Schedule::command('releases:dispatch-due-events')->everyMinute()->withoutOverlapping();
}
Schedule::command('queue:monitor-failed')->everyFiveMinutes()->withoutOverlapping();
