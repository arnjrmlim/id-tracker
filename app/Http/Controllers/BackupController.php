<?php

namespace App\Http\Controllers;

use App\Http\Requests\BackupSettingsRequest;
use App\Models\BackupHistory;
use App\Models\BackupSetting;
use App\Services\BackupService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backupService) {}

    // ── Settings page ──────────────────────────────────────────────────────────

    public function index()
    {
        Gate::authorize('admin-only');

        $settings  = BackupSetting::getInstance();
        $histories = BackupHistory::orderByDesc('created_at')->limit(20)->get();
        $isRunning = $this->backupService->isRunning();

        return view('settings.backup', compact('settings', 'histories', 'isRunning'));
    }

    // ── Save settings ──────────────────────────────────────────────────────────

    public function update(BackupSettingsRequest $request)
    {
        Gate::authorize('admin-only');

        $data = $request->validated();

        // Normalise time to H:i:s
        if (isset($data['backup_time'])) {
            $data['backup_time'] = $data['backup_time'] . ':00';
        }

        // Clear irrelevant sub-fields based on frequency
        if ($data['frequency'] !== 'weekly') {
            $data['weekly_day'] = null;
        }
        if ($data['frequency'] !== 'monthly') {
            $data['monthly_day'] = null;
        }

        BackupSetting::getInstance()->update($data);

        return redirect()->route('settings.backup.index')
            ->with('success', 'Backup settings saved successfully.');
    }

    // ── Manual backup ──────────────────────────────────────────────────────────

    public function run(Request $request)
    {
        Gate::authorize('admin-only');

        if ($this->backupService->isRunning()) {
            return back()->withErrors([
                'backup' => 'A backup is currently in progress. Please wait until it finishes.',
            ]);
        }

        $history = $this->backupService->run('manual', auth()->id());

        if ($history->status === 'success') {
            return redirect()->route('settings.backup.index')
                ->with('success', "Backup completed successfully. File: {$history->filename} ({$history->formatted_size})");
        }

        return redirect()->route('settings.backup.index')
            ->withErrors(['backup' => 'Backup failed: ' . $history->error_message]);
    }

    // ── Test configuration ─────────────────────────────────────────────────────

    public function test()
    {
        Gate::authorize('admin-only');

        $settings = BackupSetting::getInstance();
        $error    = $this->backupService->testConfiguration($settings);

        if ($error) {
            return back()->withErrors(['backup_path' => $error]);
        }

        return back()->with('success', 'Backup configuration is valid. The directory is accessible and writable.');
    }

    // ── Download ───────────────────────────────────────────────────────────────

    public function download(BackupHistory $backup)
    {
        Gate::authorize('admin-only');

        try {
            $path = $this->backupService->resolveDownloadPath($backup);
        } catch (\RuntimeException $e) {
            abort(404, $e->getMessage());
        }

        $filename = basename($path);
        $size     = filesize($path);
        $mime     = str_ends_with($filename, '.zip') ? 'application/zip' : 'application/octet-stream';

        return response()->streamDownload(function () use ($path) {
            $handle = fopen($path, 'rb');
            if ($handle) {
                while (! feof($handle)) {
                    echo fread($handle, 8192);
                    flush();
                }
                fclose($handle);
            }
        }, $filename, [
            'Content-Type'        => $mime,
            'Content-Length'      => $size,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ── Delete ─────────────────────────────────────────────────────────────────

    public function destroy(BackupHistory $backup)
    {
        Gate::authorize('admin-only');

        try {
            $this->backupService->deleteBackup($backup);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['backup' => $e->getMessage()]);
        }

        return back()->with('success', 'Backup deleted successfully.');
    }
}
