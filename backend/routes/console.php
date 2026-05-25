<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('prestataires:finalize-ia-decisions')->hourly();
Schedule::command('commandes:auto-release')->everyTenMinutes();
Schedule::command('commandes:expire-unaccepted')->hourly();
