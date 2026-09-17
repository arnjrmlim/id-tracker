@extends('layouts.app')
@section('title', 'ID Tracker')
@section('page-title', 'ID Tracker')

@push('styles')
<style>
    .filters-bar { background: #fff; border-radius: .5rem; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 1px 4px rgba(0,0,0,.06); }

    /* Selection badge shown in toolbar */
    #selection-badge {
        font-size: .8rem;
        white-space: nowrap;
    }
</style>
@endpush

@section('content')

{{-- Flash messages (error from export redirect-back) --}}
@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <i class="bi bi-exclamation-circle me-2"></i>{{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
@endif

{{-- Filters --}}
<form method="GET" action="{{ route('id-records.index') }}" id="filter-form">
<div class="filters-bar">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="form-label small fw-semibold mb-1">Search</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" placeholder="Name, ID No, Position…" value="{{ request('search') }}">
            </div>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                @foreach($statuses as $s)
                <option value="{{ $s->value }}" {{ request('status') === $s->value ? 'selected' : '' }}>{{ $s->value }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Position</label>
            <input type="text" name="position" class="form-control form-control-sm" placeholder="Filter position…" value="{{ request('position') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Date Hired From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold mb-1">Date Hired To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
        </div>
        <div class="col-12 col-md-1 d-flex gap-1">
            <button type="submit" class="btn btn-primary btn-sm w-100">
                <i class="bi bi-funnel"></i>
            </button>
            <a href="{{ route('id-records.index') }}" class="btn btn-outline-secondary btn-sm w-100" title="Clear filters">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </div>
</div>
</form>

{{-- Hidden export form (template) --}}
@can('export', \App\Models\IdRecord::class)
<form method="POST" action="{{ route('exports.template') }}" id="export-template-form">
    @csrf
    <input type="hidden" name="select_all" id="export-select-all" value="0">
    <input type="hidden" name="search"     value="{{ request('search') }}">
    <input type="hidden" name="status"     value="{{ request('status') }}">
    <input type="hidden" name="position"   value="{{ request('position') }}">
    <input type="hidden" name="date_from"  value="{{ request('date_from') }}">
    <input type="hidden" name="date_to"    value="{{ request('date_to') }}">
    <div id="export-ids-container"></div>
</form>
@endcan

{{-- Hidden bulk image download form --}}
@can('downloadImages', \App\Models\IdRecord::class)
<form method="POST" action="{{ route('id-records.bulk-download-images') }}" id="bulk-images-form">
    @csrf
    <input type="hidden" name="select_all" id="images-select-all" value="0">
    <input type="hidden" name="search"     value="{{ request('search') }}">
    <input type="hidden" name="status"     value="{{ request('status') }}">
    <input type="hidden" name="position"   value="{{ request('position') }}">
    <input type="hidden" name="date_from"  value="{{ request('date_from') }}">
    <input type="hidden" name="date_to"    value="{{ request('date_to') }}">
    <div id="images-ids-container"></div>
</form>
@endcan

{{-- Hidden bulk signature image download form --}}
@can('downloadSignatureImages', \App\Models\IdRecord::class)
<form method="POST" action="{{ route('id-records.bulk-download-signature-images') }}" id="bulk-signatures-form">
    @csrf
    <input type="hidden" name="select_all" id="signatures-select-all" value="0">
    <input type="hidden" name="search"     value="{{ request('search') }}">
    <input type="hidden" name="status"     value="{{ request('status') }}">
    <input type="hidden" name="position"   value="{{ request('position') }}">
    <input type="hidden" name="date_from"  value="{{ request('date_from') }}">
    <input type="hidden" name="date_to"    value="{{ request('date_to') }}">
    <div id="signatures-ids-container"></div>
</form>
@endcan

{{-- Table card --}}
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <span class="fw-semibold">
            <i class="bi bi-card-list me-1"></i>
            ID Records
            <span class="text-muted fw-normal">({{ number_format($records->total()) }} found)</span>
        </span>

        {{-- ── Persistent selection badge + Clear ──────────────────────────── --}}
        @canany(['export', 'downloadImages', 'downloadSignatureImages', 'bulkChangeStatus'], \App\Models\IdRecord::class)
        <span id="selection-badge" class="badge bg-primary d-none ms-1">
            <i class="bi bi-check2-square me-1"></i>
            <span id="selection-count">0</span> selected
        </span>
        <button type="button" class="btn btn-link btn-sm text-danger p-0 d-none" id="clear-selection-btn"
                title="Clear all selections">
            <i class="bi bi-x-circle me-1"></i>Clear Selection
        </button>
        @endcanany

        <div class="ms-auto d-flex gap-2 flex-wrap">
            @can('create', \App\Models\IdRecord::class)
            <a href="{{ route('id-records.create') }}" class="btn btn-success btn-sm">
                <i class="bi bi-plus-lg me-1"></i>New Record
            </a>
            @endcan

            @can('bulkChangeStatus', \App\Models\IdRecord::class)
            <button type="button" class="btn btn-warning btn-sm" id="bulk-status-btn" disabled>
                <i class="bi bi-arrow-repeat me-1"></i>Bulk Status
            </button>
            @endcan

            @can('export', \App\Models\IdRecord::class)
            <button type="button" class="btn btn-outline-secondary btn-sm" id="export-btn" disabled
                    title="Export selected records to Excel">
                <i class="bi bi-file-earmark-excel me-1"></i>Export
            </button>
            @endcan

            @can('downloadImages', \App\Models\IdRecord::class)
            <button type="button" class="btn btn-outline-info btn-sm" id="download-images-btn" disabled
                    title="Download uploaded images for selected records as ZIP">
                <i class="bi bi-images me-1"></i>Download Uploaded Images
            </button>
            @endcan

            @can('downloadSignatureImages', \App\Models\IdRecord::class)
            <button type="button" class="btn btn-outline-secondary btn-sm" id="download-signatures-btn" disabled
                    title="Download signature images for selected records as ZIP">
                <i class="bi bi-pen me-1"></i>Download Signature Images
            </button>
            @endcan
        </div>
    </div>

    {{-- Bulk action status bar (shown during ZIP generation) --}}
    <div id="bulk-status-bar" class="alert alert-info rounded-0 border-0 border-bottom mb-0 py-2 px-3 d-none" role="status">
        <span id="bulk-status-text">
            <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
            Preparing…
        </span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="records-table">
            <thead class="table-light">
                <tr>
                    @canany(['export', 'downloadImages', 'downloadSignatureImages', 'bulkChangeStatus'], \App\Models\IdRecord::class)
                    <th style="width:40px;">
                        <input type="checkbox" class="form-check-input" id="select-all"
                               title="Select / deselect all rows on this page">
                    </th>
                    @endcanany

                    @php
                        $sortBy    = request('sort_by', 'name');
                        $sortDir   = request('sort_dir', 'asc');
                        $toggleDir = $sortDir === 'asc' ? 'desc' : 'asc';
                    @endphp

                    @foreach([
                        ['id_number', 'ID No'],
                        ['name',      'Name'],
                        ['position',  'Position'],
                        ['date_hired','Date Hired'],
                        ['status',    'Status'],
                    ] as [$col, $label])
                    <th>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => $col, 'sort_dir' => $sortBy === $col ? $toggleDir : 'asc']) }}"
                           class="text-decoration-none text-dark">
                            {{ $label }}
                            @if($sortBy === $col)
                                <i class="bi bi-caret-{{ $sortDir === 'asc' ? 'up' : 'down' }}-fill small"></i>
                            @endif
                        </a>
                    </th>
                    @endforeach

                    <th>Image</th>
                    <th style="width:160px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                <tr>
                    @canany(['export', 'downloadImages', 'downloadSignatureImages', 'bulkChangeStatus'], \App\Models\IdRecord::class)
                    <td>
                        <input type="checkbox" class="form-check-input row-check"
                               value="{{ $record->id }}"
                               data-record-id="{{ $record->id }}">
                    </td>
                    @endcanany
                    <td class="fw-mono">{{ $record->id_number }}</td>
                    <td>
                        <div class="fw-semibold">{{ $record->name }}</div>
                    </td>
                    <td>{{ $record->position }}</td>
                    <td>{{ $record->date_hired_formatted }}</td>
                    <td>@include('partials.status-badge', ['status' => $record->status])</td>
                    <td>
                        @if($record->image_source === 'network' && $record->image_path)
                            <div class="d-flex flex-column align-items-start gap-1">
                                <span class="badge bg-light border text-dark">
                                    <i class="bi bi-image me-1 text-primary"></i>Available
                                </span>
                                <span class="badge bg-secondary" style="font-size:.65rem;">
                                    <i class="bi bi-hdd-network me-1"></i>Network
                                </span>
                            </div>
                        @elseif($record->image_source === 'upload' && $record->image_upload_path)
                            <div class="d-flex flex-column align-items-start gap-1">
                                <span class="badge bg-light border text-dark">
                                    <i class="bi bi-image me-1 text-primary"></i>Available
                                </span>
                                <span class="badge bg-info text-dark" style="font-size:.65rem;">
                                    <i class="bi bi-upload me-1"></i>Upload
                                </span>
                            </div>
                        @else
                            <span class="text-muted small">—</span>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('id-records.show', $record) }}" class="btn btn-outline-primary" title="View">
                                <i class="bi bi-eye"></i>
                            </a>
                            @can('update', $record)
                            <a href="{{ route('id-records.edit', $record) }}" class="btn btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @endcan
                            @can('changeStatus', $record)
                            <button type="button" class="btn btn-outline-warning change-status-btn"
                                    data-record-id="{{ $record->id }}"
                                    data-record-name="{{ $record->name }}"
                                    data-current-status="{{ $record->status }}"
                                    title="Change Status">
                                <i class="bi bi-arrow-repeat"></i>
                            </button>
                            @endcan
                            @can('delete', $record)
                            <button type="button" class="btn btn-outline-danger delete-btn"
                                    data-record-id="{{ $record->id }}"
                                    data-record-name="{{ $record->name }}"
                                    title="Delete">
                                <i class="bi bi-trash"></i>
                            </button>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                        No records found.
                        @can('create', \App\Models\IdRecord::class)
                        <a href="{{ route('id-records.create') }}">Create one</a> or
                        <a href="{{ route('imports.index') }}">import from Excel</a>.
                        @endcan
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($records->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ number_format($records->total()) }}
        </small>
        {{ $records->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>

{{-- Image download summary modal --}}
<div class="modal fade" id="imageDownloadSummaryModal" tabindex="-1"
     aria-labelledby="imageDownloadSummaryLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageDownloadSummaryLabel">
                    <i class="bi bi-check-circle text-success me-2"></i>Download Ready
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="image-summary-body"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- Change Status Modal --}}
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="status-form">
            @csrf @method('PATCH')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Change ID Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Employee: <strong id="modal-name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Status</label>
                        <div id="modal-current-status"></div>
                    </div>
                    <div class="mb-3">
                        <label for="modal-new-status" class="form-label fw-semibold">
                            New Status <span class="text-danger">*</span>
                        </label>
                        <select name="status" id="modal-new-status" class="form-select" required>
                            @foreach(\App\Enums\IdStatus::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-1">
                        <label for="modal-remarks" class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" id="modal-remarks" class="form-control" rows="3"
                                  placeholder="Optional notes…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-semibold">
                        <i class="bi bi-check-lg me-1"></i>Save Status
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Bulk Status Modal --}}
@can('bulkChangeStatus', \App\Models\IdRecord::class)
<div class="modal fade" id="bulkStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('id-records.status.bulk') }}" id="bulk-form">
            @csrf
            <div id="bulk-ids-container"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-arrow-repeat me-2"></i>Bulk Change Status
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">
                        Changing status for <strong id="bulk-count">0</strong> selected record(s).
                    </p>
                    <div class="mb-3">
                        <label for="bulk-new-status" class="form-label fw-semibold">
                            New Status <span class="text-danger">*</span>
                        </label>
                        <select name="status" id="bulk-new-status" class="form-select" required>
                            @foreach(\App\Enums\IdStatus::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-1">
                        <label for="bulk-remarks" class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" id="bulk-remarks" class="form-control" rows="3"
                                  placeholder="Optional notes for all selected records…"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-semibold">
                        <i class="bi bi-check-all me-1"></i>Apply to All Selected
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

{{-- Delete Confirm Modal --}}
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" id="delete-form">
            @csrf @method('DELETE')
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger">
                        <i class="bi bi-trash me-2"></i>Delete Record
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    Are you sure you want to delete <strong id="delete-name"></strong>?
                    <br><small class="text-muted">This is a soft delete and can be restored.</small>
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
/**
 * ID Records — persistent selection across search / filter / pagination.
 *
 * Architecture:
 *   selectedIds  — Set<string>  — the authoritative selection, stored in
 *                                 sessionStorage so it survives full-page
 *                                 navigations (filter, sort, paginate).
 *   selectAllMode — bool        — true when the user clicked the header
 *                                 "select all" checkbox; means "all records
 *                                 matching the current filters" (resolved
 *                                 server-side).  Stored in sessionStorage too.
 *
 * On every page load:
 *   1. Read selectedIds from sessionStorage.
 *   2. Tick any visible row-checkboxes whose IDs are in the set.
 *   3. Update the badge / button states.
 *
 * On checkbox change:
 *   Add/remove from selectedIds, persist, update UI.
 *
 * On "Select All" header checkbox checked:
 *   Enter selectAllMode — individual ids don't matter, server re-applies
 *   filters.  Visually tick all visible rows.
 *
 * On "Clear Selection":
 *   Empty the set, exit selectAllMode, persist, untick visible rows.
 */
(function () {
    'use strict';

    // ── sessionStorage keys ────────────────────────────────────────────────────
    const STORAGE_KEY_IDS  = 'idRecords_selectedIds';
    const STORAGE_KEY_ALL  = 'idRecords_selectAllMode';

    // ── Persistence helpers ────────────────────────────────────────────────────
    function loadSelectedIds() {
        try {
            const raw = sessionStorage.getItem(STORAGE_KEY_IDS);
            return raw ? new Set(JSON.parse(raw)) : new Set();
        } catch (_) { return new Set(); }
    }

    function saveSelectedIds(set) {
        try {
            sessionStorage.setItem(STORAGE_KEY_IDS, JSON.stringify([...set]));
        } catch (_) {}
    }

    function loadSelectAllMode() {
        try { return sessionStorage.getItem(STORAGE_KEY_ALL) === '1'; }
        catch (_) { return false; }
    }

    function saveSelectAllMode(val) {
        try { sessionStorage.setItem(STORAGE_KEY_ALL, val ? '1' : '0'); }
        catch (_) {}
    }

    // ── State ──────────────────────────────────────────────────────────────────
    const selectedIds   = loadSelectedIds();
    let   selectAllMode = loadSelectAllMode();

    // ── DOM refs ───────────────────────────────────────────────────────────────
    const selectAllCb       = document.getElementById('select-all');
    const rowChecks         = document.querySelectorAll('.row-check');
    const bulkStatusBtn     = document.getElementById('bulk-status-btn');
    const exportBtn         = document.getElementById('export-btn');
    const dlImagesBtn       = document.getElementById('download-images-btn');
    const dlSignaturesBtn   = document.getElementById('download-signatures-btn');
    const clearSelBtn       = document.getElementById('clear-selection-btn');
    const selectionBadge    = document.getElementById('selection-badge');
    const selectionCount    = document.getElementById('selection-count');
    const statusBar         = document.getElementById('bulk-status-bar');
    const statusText        = document.getElementById('bulk-status-text');

    // Forms
    const exportForm             = document.getElementById('export-template-form');
    const imagesForm             = document.getElementById('bulk-images-form');
    const signaturesForm         = document.getElementById('bulk-signatures-form');
    const exportIdsContainer     = document.getElementById('export-ids-container');
    const imagesIdsContainer     = document.getElementById('images-ids-container');
    const signaturesIdsContainer = document.getElementById('signatures-ids-container');

    // Bootstrap modals
    const statusModal  = new bootstrap.Modal(document.getElementById('statusModal'));
    const bulkModal    = document.getElementById('bulkStatusModal')
                           ? new bootstrap.Modal(document.getElementById('bulkStatusModal'))
                           : null;
    const deleteModal  = new bootstrap.Modal(document.getElementById('deleteModal'));
    const summaryModal = document.getElementById('imageDownloadSummaryModal')
                           ? new bootstrap.Modal(document.getElementById('imageDownloadSummaryModal'))
                           : null;

    // ── UI sync ────────────────────────────────────────────────────────────────
    function syncUi() {
        const count      = selectAllMode ? Infinity : selectedIds.size;
        const anyChecked = selectAllMode || selectedIds.size > 0;

        // Badge
        if (selectionBadge && selectionCount) {
            if (anyChecked) {
                selectionBadge.classList.remove('d-none');
                selectionCount.textContent = selectAllMode ? 'All filtered' : selectedIds.size;
            } else {
                selectionBadge.classList.add('d-none');
            }
        }

        // Clear button
        if (clearSelBtn) {
            clearSelBtn.classList.toggle('d-none', !anyChecked);
        }

        // Action buttons
        if (bulkStatusBtn)  bulkStatusBtn.disabled  = !anyChecked;
        if (exportBtn)      exportBtn.disabled       = !anyChecked;
        if (dlImagesBtn)    dlImagesBtn.disabled     = !anyChecked;
        if (dlSignaturesBtn) dlSignaturesBtn.disabled = !anyChecked;

        // Header checkbox
        if (selectAllCb) {
            if (selectAllMode) {
                selectAllCb.checked       = true;
                selectAllCb.indeterminate = false;
            } else {
                const visibleChecked = [...rowChecks].filter(cb => selectedIds.has(cb.value)).length;
                if (visibleChecked === 0) {
                    selectAllCb.checked       = false;
                    selectAllCb.indeterminate = false;
                } else if (visibleChecked < rowChecks.length) {
                    selectAllCb.checked       = false;
                    selectAllCb.indeterminate = true;
                } else {
                    selectAllCb.checked       = true;
                    selectAllCb.indeterminate = false;
                }
            }
        }
    }

    // ── Restore checkboxes from persisted state ────────────────────────────────
    // Called once on page load so rows that were selected before a
    // filter/pagination navigation appear ticked immediately.
    function restoreCheckboxes() {
        rowChecks.forEach(cb => {
            cb.checked = selectAllMode || selectedIds.has(cb.value);
        });
    }

    // ── Header "Select All" checkbox ───────────────────────────────────────────
    if (selectAllCb) {
        selectAllCb.addEventListener('change', () => {
            if (selectAllCb.checked) {
                // Enter select-all mode: tick all visible rows but don't
                // add their IDs to selectedIds — the server resolves via filters.
                selectAllMode = true;
                selectedIds.clear();
                rowChecks.forEach(cb => { cb.checked = true; });
            } else {
                // Uncheck header → exit select-all, clear everything
                selectAllMode = false;
                selectedIds.clear();
                rowChecks.forEach(cb => { cb.checked = false; });
            }
            saveSelectAllMode(selectAllMode);
            saveSelectedIds(selectedIds);
            syncUi();
        });
    }

    // ── Individual row checkboxes ──────────────────────────────────────────────
    rowChecks.forEach(cb => {
        cb.addEventListener('change', () => {
            // Any manual row interaction exits select-all mode.
            // If we were in select-all mode, prime selectedIds with all
            // currently visible IDs first, then apply the change.
            if (selectAllMode) {
                selectAllMode = false;
                saveSelectAllMode(false);
                // Seed set from all currently visible (ticked) rows
                rowChecks.forEach(other => {
                    if (other.checked) selectedIds.add(other.value);
                });
            }

            if (cb.checked) {
                selectedIds.add(cb.value);
            } else {
                selectedIds.delete(cb.value);
            }

            saveSelectedIds(selectedIds);
            syncUi();
        });
    });

    // ── Clear Selection button ────────────────────────────────────────────────
    if (clearSelBtn) {
        clearSelBtn.addEventListener('click', () => {
            selectAllMode = false;
            selectedIds.clear();
            saveSelectAllMode(false);
            saveSelectedIds(selectedIds);
            rowChecks.forEach(cb => { cb.checked = false; });
            syncUi();
        });
    }

    // ── Populate hidden ID inputs before form submission ───────────────────────
    function populateIdsIntoContainer(container, selectAllInput) {
        container.innerHTML = '';
        if (selectAllMode) {
            selectAllInput.value = '1';
            // No individual IDs needed — server re-applies current filter params
        } else {
            selectAllInput.value = '0';
            selectedIds.forEach(id => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'ids[]';
                inp.value = id;
                container.appendChild(inp);
            });
        }
    }

    // ── Export ─────────────────────────────────────────────────────────────────
    if (exportBtn && exportForm) {
        exportBtn.addEventListener('click', () => {
            if (!selectAllMode && selectedIds.size === 0) {
                alert('Please select at least one record to export.');
                return;
            }
            populateIdsIntoContainer(
                exportIdsContainer,
                document.getElementById('export-select-all')
            );
            exportForm.submit();
        });
    }

    // ── Shared ZIP fetch helper ────────────────────────────────────────────────
    /**
     * Submit a bulk-download form via fetch, stream the response as a Blob,
     * and show a summary modal.
     *
     * @param {HTMLFormElement} form           - The hidden form to submit
     * @param {HTMLElement}     idsContainer   - Container for hidden id inputs
     * @param {HTMLInputElement} selectAllInput - The select_all hidden field
     * @param {string}          noSelectionMsg - Alert text when nothing is selected
     * @param {string}          preparingMsg   - Status bar label while working
     * @param {string}          skippedLabel   - Summary label for skipped records
     * @param {HTMLButtonElement} btn          - The trigger button (locked during fetch)
     * @returns {Promise<void>}
     */
    async function fetchZip(form, idsContainer, selectAllInput, noSelectionMsg, preparingMsg, skippedLabel, btn) {
        if (!selectAllMode && selectedIds.size === 0) {
            alert(noSelectionMsg);
            return;
        }

        populateIdsIntoContainer(idsContainer, selectAllInput);

        const fd = new FormData(form);

        btn.disabled = true;
        showStatusBar(preparingMsg);

        try {
            const response = await fetch(form.action, {
                method:  'POST',
                body:    fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                let msg = 'An error occurred while generating the download.';
                try {
                    const json = await response.json();
                    if (json.message) msg = json.message;
                    if (json.errors)  msg = Object.values(json.errors).flat().join(' ');
                } catch (_) {}
                hideStatusBar();
                alert(msg);
                return;
            }

            const included    = parseInt(response.headers.get('X-Images-Included') || '0', 10);
            const skipped     = parseInt(response.headers.get('X-Images-Skipped')  || '0', 10);
            const total       = parseInt(response.headers.get('X-Total-Selected')  || '0', 10);
            const blob        = await response.blob();
            const disposition = response.headers.get('Content-Disposition') || '';
            const fnMatch     = disposition.match(/filename="([^"]+)"/);
            const filename    = fnMatch ? fnMatch[1] : 'download.zip';

            const url = URL.createObjectURL(blob);
            const a   = document.createElement('a');
            a.href     = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 1000);

            hideStatusBar();
            if (summaryModal) {
                document.getElementById('image-summary-body').innerHTML =
                    `<ul class="list-unstyled mb-0">
                        <li><strong>Selected records:</strong> ${total}</li>
                        <li class="text-success"><strong>Images included:</strong> ${included}</li>
                        ${skipped > 0
                            ? `<li class="text-warning"><strong>${skippedLabel}:</strong> ${skipped}</li>`
                            : ''}
                    </ul>`;
                summaryModal.show();
            }

        } catch (err) {
            hideStatusBar();
            alert('Network error while preparing the download. Please try again.');
            console.error(err);
        } finally {
            btn.disabled = (!selectAllMode && selectedIds.size === 0);
        }
    }

    // ── Download Uploaded Images button ────────────────────────────────────────
    let imagesDownloading = false;

    if (dlImagesBtn && imagesForm) {
        dlImagesBtn.addEventListener('click', async () => {
            if (imagesDownloading) return;
            imagesDownloading = true;
            await fetchZip(
                imagesForm,
                imagesIdsContainer,
                document.getElementById('images-select-all'),
                'Please select at least one record to download images.',
                'Preparing uploaded images…',
                'Records without uploaded images',
                dlImagesBtn
            );
            imagesDownloading = false;
        });
    }

    // ── Download Signature Images button ───────────────────────────────────────
    let signaturesDownloading = false;

    if (dlSignaturesBtn && signaturesForm) {
        dlSignaturesBtn.addEventListener('click', async () => {
            if (signaturesDownloading) return;
            signaturesDownloading = true;
            await fetchZip(
                signaturesForm,
                signaturesIdsContainer,
                document.getElementById('signatures-select-all'),
                'Please select at least one record to download signature images.',
                'Preparing signature images…',
                'Records without signature images',
                dlSignaturesBtn
            );
            signaturesDownloading = false;
        });
    }

    function showStatusBar(msg) {
        if (!statusBar || !statusText) return;
        statusText.innerHTML =
            `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${msg}`;
        statusBar.classList.remove('d-none');
    }

    function hideStatusBar() {
        statusBar?.classList.add('d-none');
    }

    // ── Bulk Status ────────────────────────────────────────────────────────────
    if (bulkStatusBtn && bulkModal) {
        bulkStatusBtn.addEventListener('click', () => {
            // Bulk status only operates on currently visible checked rows
            // (existing behaviour — not changed by this feature)
            const checked = [...document.querySelectorAll('.row-check:checked')];
            document.getElementById('bulk-count').textContent = checked.length;
            const container = document.getElementById('bulk-ids-container');
            container.innerHTML = '';
            checked.forEach(cb => {
                const inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'ids[]';
                inp.value = cb.value;
                container.appendChild(inp);
            });
            bulkModal.show();
        });
    }

    // ── Single status change ───────────────────────────────────────────────────
    document.querySelectorAll('.change-status-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.recordId;
            document.getElementById('modal-name').textContent         = btn.dataset.recordName;
            document.getElementById('modal-current-status').innerHTML =
                `<span class="badge bg-secondary badge-status">${btn.dataset.currentStatus}</span>`;
            document.getElementById('modal-new-status').value         = btn.dataset.currentStatus;
            document.getElementById('modal-remarks').value            = '';
            document.getElementById('status-form').action             =
                `{{ rtrim(url('id-records'), '/') }}/${id}/status`;
            statusModal.show();
        });
    });

    // ── Delete ─────────────────────────────────────────────────────────────────
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('delete-name').textContent = btn.dataset.recordName;
            document.getElementById('delete-form').action      =
                `{{ rtrim(url('id-records'), '/') }}/${btn.dataset.recordId}`;
            deleteModal.show();
        });
    });

    // ── Boot ───────────────────────────────────────────────────────────────────
    restoreCheckboxes();
    syncUi();

}());
</script>
@endpush
@endsection
