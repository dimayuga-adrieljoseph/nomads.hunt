<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run claim expiration every minute in production.
// For demo: run `php artisan claims:expire` manually, or use Force Expire in the admin UI.
Schedule::command('claims:expire')->everyMinute();
