<?php

use App\Models\BackupSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── Automatic Backup Scheduler ────────────────────────────────────────────────
// Laravel's scheduler calls this closure every minute.
// The closure reads live settings from the database so schedule changes
// take effect without restarting any process.

Schedule::call(function () {
    $settings = BackupSetting::getInstance();

    if (! $settings->enabled) {
        return; // automatic backup is disabled
    }

    $now        = now();
    $configTime = substr($settings->backup_time ?? '02:00:00', 0, 5); // "HH:MM"
    $currentHM  = $now->format('H:i');

    if ($currentHM !== $configTime) {
        return; // not the right minute
    }

    $shouldRun = match ($settings->frequency) {
        'daily'   => true,
        'weekly'  => $now->dayOfWeek === (int) $settings->weekly_day,
        'monthly' => $now->day === (int) $settings->monthly_day,
        default   => false,
    };

    if ($shouldRun) {
        Artisan::call('backup:run');
    }
})->everyMinute()->name('automatic-backup')->withoutOverlapping(30);
