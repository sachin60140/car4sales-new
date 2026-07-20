<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Scheduled tasks. On a server, run `php artisan schedule:work` (or a cron entry
| calling `schedule:run` each minute). XAMPP has no cron, so the digest can also
| be triggered manually with `php artisan reports:daily-digest`.
*/
Schedule::command('reports:daily-digest')->dailyAt('20:00')->withoutOverlapping();
