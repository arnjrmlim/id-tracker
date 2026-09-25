<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BulkImageDownloadController;
use App\Http\Controllers\IdRecordApprovalController;
use App\Http\Controllers\IdRecordImageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\IdRecordController;
use App\Http\Controllers\IdStatusController;
use App\Http\Controllers\IdStatusHistoryController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// ── Authentication ─────────────────────────────────────────────────────────────
Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('login', [LoginController::class, 'login'])->name('login.post');
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// ── Authenticated routes ───────────────────────────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // ID Records — CRUD
    Route::resource('id-records', IdRecordController::class);

    // Image / signature streaming (serves local/network paths safely)
    Route::get('id-records/{id_record}/image',     [IdRecordImageController::class, 'image'])->name('id-records.image');
    Route::get('id-records/{id_record}/signature', [IdRecordImageController::class, 'signature'])->name('id-records.signature');

    // Soft-deleted records (admin only)
    Route::get('id-records-trashed', [IdRecordController::class, 'trashed'])->name('id-records.trashed');
    Route::patch('id-records-trashed/{id}/restore', [IdRecordController::class, 'restore'])->name('id-records.restore');

    // Status change (admin only — enforced in policy + service + form request)
    Route::patch('id-records/{id_record}/status', [IdStatusController::class, 'update'])->name('id-records.status.update');
    Route::post('id-records/bulk-status', [IdStatusController::class, 'bulkUpdate'])->name('id-records.status.bulk');

    // Status history (admin only)
    Route::get('status-history', [IdStatusHistoryController::class, 'index'])->name('history.index');

    // ID Record approval workflow (admin only for approve/reject)
    Route::prefix('id-requests')->name('id-requests.')->group(function () {
        Route::get('/', [IdRecordApprovalController::class, 'index'])->name('index');
        Route::get('/my-requests', [IdRecordApprovalController::class, 'myRequests'])->name('my-requests');
        Route::get('/{id_record}', [IdRecordApprovalController::class, 'show'])->name('show');
        Route::post('/{id_record}/approve', [IdRecordApprovalController::class, 'approve'])->name('approve');
        Route::post('/{id_record}/reject', [IdRecordApprovalController::class, 'reject'])->name('reject');
    });

    // Import
    Route::get('import', [ImportController::class, 'index'])->name('imports.index');
    Route::post('import/preview', [ImportController::class, 'preview'])->name('imports.preview');
    Route::post('import/store', [ImportController::class, 'store'])->name('imports.store');
    Route::get('import/result/{logId}', [ImportController::class, 'result'])->name('imports.result');
    Route::get('import/template', [ImportController::class, 'downloadTemplate'])->name('imports.template');

    // Export
    Route::get('export', [ExportController::class, 'index'])->name('exports.index');
    Route::post('export/template', [ExportController::class, 'exportTemplate'])->name('exports.template');
    Route::post('export/report', [ExportController::class, 'exportReport'])->name('exports.report');

    // Bulk image download (ZIP)
    Route::post('id-records/bulk-download-images',           [BulkImageDownloadController::class, 'download'])->name('id-records.bulk-download-images');
    Route::post('id-records/bulk-download-signature-images', [BulkImageDownloadController::class, 'downloadSignatures'])->name('id-records.bulk-download-signature-images');

    // User management (admin only via policy)
    Route::resource('users', UserController::class);
    Route::patch('users/{user}/activate', [UserController::class, 'activate'])->name('users.activate');
    Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');

    // Settings — Automatic Backup (admin only — enforced via Gate::authorize inside controller)
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('backup',           [BackupController::class, 'index'])  ->name('backup.index');
        Route::put('backup',           [BackupController::class, 'update']) ->name('backup.update');
        Route::post('backup/run',      [BackupController::class, 'run'])    ->name('backup.run');
        Route::post('backup/test',     [BackupController::class, 'test'])   ->name('backup.test');
        Route::get('backup/{backup}/download', [BackupController::class, 'download'])->name('backup.download');
        Route::delete('backup/{backup}',       [BackupController::class, 'destroy']) ->name('backup.destroy');
    });
});
