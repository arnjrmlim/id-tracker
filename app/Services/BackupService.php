<?php

namespace App\Services;

use App\Models\BackupHistory;
use App\Models\BackupSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class BackupService
{
    /** Cache key used to prevent concurrent backups. */
    private const LOCK_KEY = 'backup_running';

    /** Lock TTL in seconds — long enough to cover a large dump. */
    private const LOCK_TTL = 1800;

    /** Only filenames matching this prefix are managed/deleted by us. */
    public const FILENAME_PREFIX = 'IDTracker_Backup_';

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Run a backup (manual or automatic).
     *
     * @param  string   $type       'manual' | 'automatic'
     * @param  int|null $initiatorId  User ID for manual backups, null for auto
     * @return BackupHistory
     */
    public function run(string $type = 'manual', ?int $initiatorId = null): BackupHistory
    {
        // ── Concurrency guard ──────────────────────────────────────────────────
        if (Cache::has(self::LOCK_KEY)) {
            throw new \RuntimeException('A backup is currently in progress. Please wait until it finishes.');
        }

        Cache::put(self::LOCK_KEY, true, self::LOCK_TTL);

        $history = BackupHistory::create([
            'type'       => $type,
            'status'     => 'running',
            'started_at' => now(),
            'created_by' => $initiatorId,
        ]);

        try {
            $settings = BackupSetting::getInstance();

            // Validate / create backup directory
            $backupDir = $this->resolveBackupDirectory($settings);

            $timestamp = now()->format('Y-m-d_His');
            $basename  = self::FILENAME_PREFIX . $timestamp;

            if ($settings->include_uploaded_files) {
                $filename = $basename . '.zip';
                $fullPath = $backupDir . DIRECTORY_SEPARATOR . $filename;
                $this->createZipBackup($fullPath, $settings);
            } else {
                $filename = $basename . '.sql';
                $fullPath = $backupDir . DIRECTORY_SEPARATOR . $filename;
                $this->dumpDatabase($fullPath);
            }

            $size = file_exists($fullPath) ? filesize($fullPath) : 0;

            // Mark successful
            $history->update([
                'filename'     => $filename,
                'path'         => $fullPath,
                'size'         => $size,
                'status'       => 'success',
                'completed_at' => now(),
            ]);

            // Update last run timestamp on settings
            $settings->update(['last_run_at' => now()]);

            // Run retention cleanup (non-fatal)
            $this->applyRetention($settings, $backupDir);

            Log::info("Backup completed: {$filename} ({$size} bytes, type={$type})");

        } catch (\Throwable $e) {
            $safeMessage = $this->sanitizeErrorMessage($e->getMessage());

            $history->update([
                'status'        => 'failed',
                'error_message' => $safeMessage,
                'completed_at'  => now(),
            ]);

            Log::error("Backup failed [{$type}]: " . $e->getMessage());
        } finally {
            Cache::forget(self::LOCK_KEY);
        }

        return $history->fresh();
    }

    /** Returns true if a backup is currently running. */
    public function isRunning(): bool
    {
        return Cache::has(self::LOCK_KEY);
    }

    /**
     * Validate that the configured backup path is usable.
     * Returns null on success, or a human-readable error string.
     */
    public function testConfiguration(BackupSetting $settings): ?string
    {
        try {
            $this->resolveBackupDirectory($settings);
            return null;
        } catch (\Throwable $e) {
            return $this->sanitizeErrorMessage($e->getMessage());
        }
    }

    /**
     * Securely stream a backup file for download.
     * Verifies the file belongs to a known BackupHistory record and
     * lives inside the configured backup directory.
     *
     * @throws \RuntimeException on path-traversal or invalid record
     */
    public function resolveDownloadPath(BackupHistory $history): string
    {
        if ($history->status !== 'success' || empty($history->path)) {
            throw new \RuntimeException('This backup is not available for download.');
        }

        $settings  = BackupSetting::getInstance();
        $backupDir = rtrim(str_replace('/', DIRECTORY_SEPARATOR, $settings->backup_path ?? ''), DIRECTORY_SEPARATOR);
        $realBackup = realpath($backupDir);
        $realFile   = realpath($history->path);

        if ($realFile === false || $realBackup === false) {
            throw new \RuntimeException('Backup file not found.');
        }

        // Prevent path traversal — file must be inside the backup directory
        if (! str_starts_with($realFile, $realBackup . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Invalid backup path.');
        }

        // Extra safety: filename must match our controlled prefix
        if (! str_starts_with(basename($realFile), self::FILENAME_PREFIX)) {
            throw new \RuntimeException('Invalid backup filename.');
        }

        return $realFile;
    }

    /**
     * Delete a backup file and its history record.
     * Only deletes files that belong to our backup records and use
     * our controlled filename prefix.
     *
     * @throws \RuntimeException on path-traversal or invalid record
     */
    public function deleteBackup(BackupHistory $history): void
    {
        if (! empty($history->path) && file_exists($history->path)) {
            $filename = basename($history->path);

            // Only delete files with our controlled prefix — never arbitrary files
            if (! str_starts_with($filename, self::FILENAME_PREFIX)) {
                throw new \RuntimeException('File does not belong to the backup system.');
            }

            // Verify file is inside the configured backup directory
            $this->resolveDownloadPath($history); // throws if path is invalid

            @unlink($history->path);
        }

        $history->delete();
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * Resolve and validate the backup directory.
     * Creates it if it does not exist (only when creation is safe).
     *
     * @throws \RuntimeException on invalid/unsafe/unwritable path
     */
    private function resolveBackupDirectory(BackupSetting $settings): string
    {
        $path = trim($settings->backup_path ?? '');

        if (empty($path)) {
            throw new \RuntimeException('Backup directory is not configured. Please set a backup path in Settings.');
        }

        // Normalize separators for Windows
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);

        // Basic safety: refuse paths that look like application source directories
        $appBase   = realpath(base_path());
        $realPath  = realpath($path);

        if ($realPath === false) {
            // Directory does not exist yet — try to create it
            if (! @mkdir($path, 0755, true)) {
                throw new \RuntimeException("Cannot create backup directory: {$path}");
            }
            $realPath = realpath($path);
        }

        if ($realPath === false) {
            throw new \RuntimeException("Backup directory could not be resolved: {$path}");
        }

        // Do not allow writing inside the application source tree
        if ($appBase && str_starts_with($realPath, $appBase)) {
            throw new \RuntimeException(
                'Backup directory must not be inside the application source directory. Choose an external location.'
            );
        }

        if (! is_dir($realPath)) {
            throw new \RuntimeException("Configured backup path is not a directory: {$realPath}");
        }

        if (! is_writable($realPath)) {
            throw new \RuntimeException("Backup directory is not writable: {$realPath}");
        }

        return $realPath;
    }

    /**
     * Run mysqldump and write a .sql file.
     * Credentials are passed through a temporary options file so they
     * never appear in the process argument list or logs.
     */
    private function dumpDatabase(string $outputPath): void
    {
        $db       = config('database.connections.mysql');
        $host     = $db['host']     ?? '127.0.0.1';
        $port     = $db['port']     ?? '3306';
        $database = $db['database'] ?? '';
        $username = $db['username'] ?? 'root';
        $password = $db['password'] ?? '';

        $mysqldump = $this->findMysqldump();

        // Write credentials to a temp file so they are never in the command string
        $cnfFile = tempnam(sys_get_temp_dir(), 'idtracker_my_') . '.cnf';
        file_put_contents($cnfFile, implode("\n", [
            '[client]',
            "host={$host}",
            "port={$port}",
            "user={$username}",
            "password={$password}",
        ]));
        chmod($cnfFile, 0600);

        try {
            $cmd = sprintf(
                '"%s" --defaults-extra-file="%s" --single-transaction --routines --triggers --hex-blob "%s" > "%s" 2>&1',
                $mysqldump,
                $cnfFile,
                $database,
                $outputPath
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0) {
                throw new \RuntimeException('mysqldump exited with code ' . $exitCode . '. Check that the database is accessible.');
            }

            if (! file_exists($outputPath) || filesize($outputPath) === 0) {
                throw new \RuntimeException('mysqldump produced an empty output file.');
            }
        } finally {
            @unlink($cnfFile);
        }
    }

    /**
     * Create a .zip containing the SQL dump and optionally uploaded image files.
     * Network-referenced files (Z:\... or \\...) are never included.
     */
    private function createZipBackup(string $zipPath, BackupSetting $settings): void
    {
        // First generate the SQL dump into a temp file
        $sqlTmp = tempnam(sys_get_temp_dir(), 'idtracker_sql_') . '.sql';
        try {
            $this->dumpDatabase($sqlTmp);

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new \RuntimeException("Cannot create zip archive: {$zipPath}");
            }

            // Add SQL dump
            $zip->addFile($sqlTmp, 'database.sql');

            // Add Laravel-managed uploaded files (id-images/ and signatures/ only)
            if ($settings->include_uploaded_files) {
                $publicDisk = Storage::disk('public');
                foreach (['id-images', 'signatures'] as $dir) {
                    if ($publicDisk->exists($dir)) {
                        foreach ($publicDisk->allFiles($dir) as $file) {
                            $abs = $publicDisk->path($file);
                            if (file_exists($abs)) {
                                $zip->addFile($abs, 'uploads/' . $file);
                            }
                        }
                    }
                }
            }

            $zip->close();

            if (! file_exists($zipPath) || filesize($zipPath) === 0) {
                throw new \RuntimeException('Zip archive was created but is empty.');
            }
        } finally {
            @unlink($sqlTmp);
        }
    }

    /**
     * Delete backup files older than the retention period.
     * Only touches files with our controlled prefix — never arbitrary files.
     */
    private function applyRetention(BackupSetting $settings, string $backupDir): void
    {
        $days = (int) ($settings->retention_days ?? 0);
        if ($days <= 0) {
            return;
        }

        $cutoff = now()->subDays($days)->getTimestamp();

        // Only consider DB-tracked backups for deletion
        $old = BackupHistory::where('status', 'success')
            ->where('created_at', '<', now()->subDays($days))
            ->get();

        foreach ($old as $history) {
            if (empty($history->path)) {
                continue;
            }
            $filename = basename($history->path);
            // Extra safety: only delete our own files
            if (! str_starts_with($filename, self::FILENAME_PREFIX)) {
                continue;
            }
            if (file_exists($history->path)) {
                @unlink($history->path);
            }
            $history->delete();
        }
    }

    /**
     * Locate the mysqldump executable.
     * Checks the env-configured path first, then common XAMPP locations.
     */
    private function findMysqldump(): string
    {
        // Allow override via .env: MYSQLDUMP_PATH=C:\xampp\mysql\bin\mysqldump.exe
        $envPath = env('MYSQLDUMP_PATH');
        if ($envPath && file_exists($envPath)) {
            return $envPath;
        }

        $candidates = [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp64\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            'mysqldump',       // if in system PATH
            'mysqldump.exe',
        ];

        foreach ($candidates as $candidate) {
            if (@file_exists($candidate)) {
                return $candidate;
            }
            // For bare commands, try `where` on Windows
            if (! str_contains($candidate, DIRECTORY_SEPARATOR)) {
                exec("where {$candidate} 2>NUL", $out, $code);
                if ($code === 0 && ! empty($out[0])) {
                    return trim($out[0]);
                }
            }
        }

        throw new \RuntimeException(
            'mysqldump executable not found. Set MYSQLDUMP_PATH in your .env file ' .
            '(e.g. MYSQLDUMP_PATH=C:\\xampp\\mysql\\bin\\mysqldump.exe).'
        );
    }

    /**
     * Strip potentially sensitive content from error messages before storing/displaying.
     * Removes anything that looks like a password= value or file path with credentials.
     */
    private function sanitizeErrorMessage(string $message): string
    {
        // Remove password= values
        $message = preg_replace('/password\s*=\s*\S+/i', 'password=[hidden]', $message);
        // Remove -p<password> style args
        $message = preg_replace('/-p\S+/', '-p[hidden]', $message);
        return $message;
    }
}
