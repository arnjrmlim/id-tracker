<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    protected $table = 'backup_settings';

    protected $fillable = [
        'enabled',
        'frequency',
        'weekly_day',
        'monthly_day',
        'backup_time',
        'backup_path',
        'include_uploaded_files',
        'retention_days',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled'                => 'boolean',
            'include_uploaded_files' => 'boolean',
            'last_run_at'            => 'datetime',
            'weekly_day'             => 'integer',
            'monthly_day'            => 'integer',
            'retention_days'         => 'integer',
        ];
    }

    /**
     * Return the singleton settings row, creating it with defaults if missing.
     */
    public static function getInstance(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            [
                'enabled'                => false,
                'frequency'              => 'daily',
                'backup_time'            => '02:00:00',
                'backup_path'            => storage_path('app/backups'),
                'include_uploaded_files' => false,
                'retention_days'         => 30,
            ]
        );
    }

    public function getFormattedTimeAttribute(): string
    {
        // Convert 24-hour stored time to H:i for <input type="time">
        return substr($this->backup_time ?? '02:00:00', 0, 5);
    }

    public function getWeekDayOptions(): array
    {
        return [
            0 => 'Sunday',    1 => 'Monday', 2 => 'Tuesday',
            3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday',
            6 => 'Saturday',
        ];
    }
}
