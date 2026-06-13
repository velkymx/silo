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

// Admin-configured automatic backups. The frequency lives in the settings table;
// each cadence is registered and only fires when it matches the saved choice.
$backupFrequency = fn () => \App\Models\Setting::get('backup.frequency', 'off');
Schedule::command('backup:run')->dailyAt('02:00')->when(fn () => $backupFrequency() === 'daily');
Schedule::command('backup:run')->weeklyOn(0, '02:00')->when(fn () => $backupFrequency() === 'weekly');
Schedule::command('backup:run')->monthlyOn(1, '02:00')->when(fn () => $backupFrequency() === 'monthly');
