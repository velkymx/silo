<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Permanently remove items that have sat in the trash past the retention window.
Schedule::command('trash:purge')->daily();

// Safety net: re-queue uploads whose processing stalled (dead/restarted worker).
Schedule::command('files:reconcile')->everyTenMinutes();
