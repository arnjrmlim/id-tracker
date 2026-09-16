@extends('layouts.app')
@section('title', 'Backup Settings')
@section('page-title', 'Settings — Automatic Backup')

@push('styles')
<style>
    .section-card { margin-bottom: 1.5rem; }
    .freq-extra   { display: none; }
</style>
@endpush

@section('content')

@if($isRunning)
<div class="alert alert-warning d-flex align-items-center gap-2">
    <div class="spinner-border spinner-border-sm flex-shrink-0" role="status"></div>
    <strong>A backup is currently running.</strong> Manual backup is unavailable until it completes.
</div>
@endif

<div class="row g-4">

    {{-- ── Left column: Settings form ── --}}
    <div class="col-lg-7">

        {{-- Backup Settings ── --}}
        <div class="card shadow-sm section-card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-gear-fill text-primary"></i>
                <strong>Automatic Backup Settings</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('settings.backup.update') }}" id="settings-form">
                    @csrf @method('PUT')

                    {{-- Enable / Disable --}}
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Automatic Backup</label>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   role="switch" id="enabled" name="enabled"
                                   value="1" {{ $settings->enabled ? 'checked' : '' }}>
                            <label class="form-check-label" for="enabled">
                                {{ $settings->enabled ? 'Enabled' : 'Disabled' }}
                            </label>
                        </div>
                        @error('enabled') <div class="text-danger small">{{ $message }}</div> @enderror
                    </div>

                    <hr>

                    {{-- Frequency --}}
                    <div class="mb-3">
                        <label for="frequency" class="form-label fw-semibold">Backup Frequency</label>
                        <select name="frequency" id="frequency" class="form-select @error('frequency') is-invalid @enderror">
                            <option value="daily"   {{ $settings->frequency === 'daily'   ? 'selected' : '' }}>Daily</option>
                            <option value="weekly"  {{ $settings->frequency === 'weekly'  ? 'selected' : '' }}>Weekly</option>
                            <option value="monthly" {{ $settings->frequency === 'monthly' ? 'selected' : '' }}>Monthly</option>
                        </select>
                        @error('frequency') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Weekly day (shown only for Weekly) --}}
                    <div id="weekly-panel" class="mb-3 freq-extra">
                        <label for="weekly_day" class="form-label fw-semibold">Day of Week</label>
                        <select name="weekly_day" id="weekly_day" class="form-select @error('weekly_day') is-invalid @enderror">
                            @foreach($settings->getWeekDayOptions() as $val => $label)
                            <option value="{{ $val }}" {{ (int)$settings->weekly_day === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('weekly_day') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Monthly day (shown only for Monthly) --}}
                    <div id="monthly-panel" class="mb-3 freq-extra">
                        <label for="monthly_day" class="form-label fw-semibold">Day of Month <small class="text-muted">(1–28)</small></label>
                        <input type="number" name="monthly_day" id="monthly_day"
                               class="form-control @error('monthly_day') is-invalid @enderror"
                               min="1" max="28" value="{{ old('monthly_day', $settings->monthly_day) }}">
                        @error('monthly_day') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    {{-- Backup Time --}}
                    <div class="mb-3">
                        <label for="backup_time" class="form-label fw-semibold">Backup Time</label>
                        <input type="time" name="backup_time" id="backup_time"
                               class="form-control @error('backup_time') is-invalid @enderror"
                               value="{{ old('backup_time', $settings->formatted_time) }}">
                        <div class="form-text">Server timezone: <strong>{{ config('app.timezone', 'UTC') }}</strong></div>
                        @error('backup_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <hr>

                    {{-- Backup Path --}}
                    <div class="mb-3">
                        <label for="backup_path" class="form-label fw-semibold">Backup Directory</label>
                        <div class="input-group">
                            <input type="text" name="backup_path" id="backup_path"
                                   class="form-control @error('backup_path') is-invalid @enderror"
                                   value="{{ old('backup_path', $settings->backup_path) }}"
                                   placeholder="C:\IDTracker\Backups">
                            <button type="button" class="btn btn-outline-secondary" id="test-path-btn">
                                <i class="bi bi-check-circle me-1"></i>Test
                            </button>
                        </div>
                        <div class="form-text">Windows paths supported, e.g. <code>C:\IDTracker\Backups</code></div>
                        @error('backup_path') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    {{-- Include uploaded files --}}
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="include_uploaded_files"
                                   name="include_uploaded_files" value="1"
                                   {{ $settings->include_uploaded_files ? 'checked' : '' }}>
                            <label class="form-check-label" for="include_uploaded_files">
                                Include uploaded ID images and signatures
                            </label>
                        </div>
                        <div class="form-text text-muted ps-4">
                            Packages Laravel-managed uploads (<code>id-images/</code>, <code>signatures/</code>) into a .zip.
                            Network-referenced files are never copied.
                        </div>
                    </div>

                    <hr>

                    {{-- Retention --}}
                    <div class="mb-4">
                        <label for="retention_days" class="form-label fw-semibold">Retention Period</label>
                        <div class="input-group" style="max-width:200px;">
                            <input type="number" name="retention_days" id="retention_days"
                                   class="form-control @error('retention_days') is-invalid @enderror"
                                   min="1" max="3650"
                                   value="{{ old('retention_days', $settings->retention_days) }}">
                            <span class="input-group-text">days</span>
                        </div>
                        <div class="form-text">Backups older than this are automatically removed. Min: 1 day.</div>
                        @error('retention_days') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-floppy me-1"></i>Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>

    {{-- ── Right column: Manual backup + status ── --}}
    <div class="col-lg-5">

        {{-- Manual Backup --}}
        <div class="card shadow-sm section-card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-database-fill-up text-success"></i>
                <strong>Manual Backup</strong>
            </div>
            <div class="card-body">
                @error('backup')
                <div class="alert alert-danger py-2 small">
                    <i class="bi bi-exclamation-triangle me-1"></i>{{ $message }}
                </div>
                @enderror

                <form method="POST" action="{{ route('settings.backup.run') }}" id="run-form">
                    @csrf
                    <button type="submit" class="btn btn-success w-100 fw-semibold {{ $isRunning ? 'disabled' : '' }}"
                            {{ $isRunning ? 'disabled' : '' }}
                            onclick="this.disabled=true; this.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\' role=\'status\'></span>Running…'; this.form.submit();">
                        <i class="bi bi-play-fill me-1"></i>Backup Now
                    </button>
                </form>

                <hr>

                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Last Backup</dt>
                    <dd class="col-7">
                        {{ $settings->last_run_at ? $settings->last_run_at->format('m/d/Y g:i A') : 'Never' }}
                    </dd>
                    <dt class="col-5 text-muted">Schedule</dt>
                    <dd class="col-7">
                        @if($settings->enabled)
                            <span class="badge bg-success">Enabled</span>
                            {{ ucfirst($settings->frequency) }}
                            @if($settings->frequency === 'weekly')
                                ({{ $settings->getWeekDayOptions()[$settings->weekly_day] ?? '—' }})
                            @elseif($settings->frequency === 'monthly')
                                (Day {{ $settings->monthly_day }})
                            @endif
                            at {{ $settings->formatted_time }}
                        @else
                            <span class="badge bg-secondary">Disabled</span>
                        @endif
                    </dd>
                    <dt class="col-5 text-muted">Timezone</dt>
                    <dd class="col-7">{{ config('app.timezone', 'UTC') }}</dd>
                </dl>
            </div>
        </div>

        {{-- Windows Task Scheduler instructions --}}
        <div class="card shadow-sm section-card border-info">
            <div class="card-header text-info fw-semibold py-2">
                <i class="bi bi-windows me-2"></i>Windows Task Scheduler Setup
            </div>
            <div class="card-body small">
                <p class="mb-2">For automatic backups to run, add a Windows Scheduled Task that runs every minute:</p>
                <div class="bg-light border rounded p-2 mb-2">
                    <strong>Program:</strong><br>
                    <code>C:\xampp\php\php.exe</code>
                </div>
                <div class="bg-light border rounded p-2 mb-2">
                    <strong>Arguments:</strong><br>
                    <code>artisan schedule:run</code>
                </div>
                <div class="bg-light border rounded p-2 mb-2">
                    <strong>Start in:</strong><br>
                    <code>{{ base_path() }}</code>
                </div>
                <div class="bg-light border rounded p-2">
                    <strong>Trigger:</strong> Every 1 minute, indefinitely
                </div>
                <p class="mt-2 mb-0 text-muted">Or run once in a console to test:</p>
                <code class="d-block bg-light border rounded p-2 mt-1">php artisan schedule:run</code>
            </div>
        </div>

    </div>
</div>

{{-- ── Backup History ── --}}
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-clock-history text-primary"></i>
        <strong>Backup History</strong>
        <span class="text-muted fw-normal small">(last 20)</span>
    </div>

    @if($histories->isEmpty())
    <div class="card-body text-center text-muted py-4">
        <i class="bi bi-database-x fs-3 d-block mb-2"></i>No backups recorded yet.
    </div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date / Time</th>
                    <th>Type</th>
                    <th>Filename</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Initiated By</th>
                    <th style="width:140px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($histories as $h)
                <tr>
                    <td class="text-nowrap small text-muted">
                        {{ $h->started_at ? $h->started_at->format('m/d/Y g:i A') : $h->created_at->format('m/d/Y g:i A') }}
                    </td>
                    <td>
                        <span class="badge {{ $h->type === 'manual' ? 'bg-primary' : 'bg-secondary' }}">
                            {{ ucfirst($h->type) }}
                        </span>
                    </td>
                    <td class="small">
                        {{ $h->filename ?? '—' }}
                        @if($h->status === 'failed' && $h->error_message)
                        <div class="text-danger" style="font-size:.75rem;">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ Str::limit($h->error_message, 80) }}
                        </div>
                        @endif
                    </td>
                    <td class="small">{{ $h->formatted_size }}</td>
                    <td>
                        @if($h->status === 'success')
                            <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Success</span>
                        @elseif($h->status === 'failed')
                            <span class="badge bg-danger"><i class="bi bi-x-lg me-1"></i>Failed</span>
                        @else
                            <span class="badge bg-warning text-dark">
                                <span class="spinner-border spinner-border-sm me-1"></span>Running
                            </span>
                        @endif
                    </td>
                    <td class="small">{{ $h->createdBy?->name ?? ($h->type === 'automatic' ? 'Scheduler' : '—') }}</td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            @if($h->status === 'success')
                            <a href="{{ route('settings.backup.download', $h) }}"
                               class="btn btn-outline-primary" title="Download">
                                <i class="bi bi-download"></i>
                            </a>
                            @endif
                            <button type="button"
                                    class="btn btn-outline-danger delete-backup-btn"
                                    data-backup-id="{{ $h->id }}"
                                    data-filename="{{ $h->filename ?? 'this backup' }}"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

{{-- Delete Confirm Modal --}}
<div class="modal fade" id="deleteBackupModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" id="delete-backup-form">
            @csrf @method('DELETE')
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-trash me-2"></i>Delete Backup
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    Delete <strong id="delete-backup-name"></strong>?
                    <br><small class="text-muted">This cannot be undone.</small>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// ── Frequency panel toggle ──
const freqSelect = document.getElementById('frequency');
function updateFreqPanels() {
    document.getElementById('weekly-panel').style.display  = freqSelect.value === 'weekly'  ? '' : 'none';
    document.getElementById('monthly-panel').style.display = freqSelect.value === 'monthly' ? '' : 'none';
}
freqSelect?.addEventListener('change', updateFreqPanels);
updateFreqPanels();

// ── Enabled switch label ──
document.getElementById('enabled')?.addEventListener('change', function () {
    this.nextElementSibling.textContent = this.checked ? 'Enabled' : 'Disabled';
});

// ── Test path button ──
document.getElementById('test-path-btn')?.addEventListener('click', function () {
    document.getElementById('settings-form').action = '{{ route('settings.backup.test') }}';
    document.getElementById('settings-form').method = 'POST';
    // Override method field
    let m = document.querySelector('#settings-form input[name="_method"]');
    if (m) m.value = 'POST';
    document.getElementById('settings-form').submit();
    // Restore after submit (in case prevented)
    document.getElementById('settings-form').action = '{{ route('settings.backup.update') }}';
});

// ── Delete backup modal ──
const deleteModal = new bootstrap.Modal(document.getElementById('deleteBackupModal'));
document.querySelectorAll('.delete-backup-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('delete-backup-name').textContent = btn.dataset.filename;
        document.getElementById('delete-backup-form').action =
            '{{ rtrim(url('settings/backup'), '/') }}/' + btn.dataset.backupId;
        deleteModal.show();
    });
});
</script>
@endpush
@endsection
