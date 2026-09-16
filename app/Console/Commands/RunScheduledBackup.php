<?php

namespace App\Console\Commands;

use App\Models\BackupSetting;
use App\Services\BackupService;
use Illuminate\Console\Command;

class RunScheduledBackup extends Command
{
    protected $signature   = 'backup:run {--force : Run immediately, bypassing schedule/enabled check}';
    protected $description = 'Run the automatic database backup if it is due according to the configured schedule.';

    public function handle(BackupService $backupService): int
    {
        $settings = BackupSetting::getInstance();

        if (! $this->option('force') && ! $settings->enabled) {
            $this->info('Automatic backup is disabled. Skipping.');
            return self::SUCCESS;
        }

        if ($backupService->isRunning()) {
            $this->warn('A backup is already in progress. Skipping.');
            return self::SUCCESS;
        }

        $this->info('Starting automatic backup…');

        $history = $backupService->run('automatic', null);

        if ($history->status === 'success') {
            $this->info("Backup completed: {$history->filename} ({$history->formatted_size})");
            return self::SUCCESS;
        }

        $this->error('Backup failed: ' . $history->error_message);
        return self::FAILURE;
    }
}
