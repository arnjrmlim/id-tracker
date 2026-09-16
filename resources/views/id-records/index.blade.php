@extends('layouts.app')
@section('title', 'ID Tracker')
@section('page-title', 'ID Tracker')

@push('styles')
<style>
    .filters-bar { background: #fff; border-radius: .5rem; padding: 1rem; margin-bottom: 1rem; box-shadow: 0 1px 4px rgba(0,0,0,.06); }
</style>
@endpush

@section('content')

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
            <a href="{{ route('id-records.index') }}" class="btn btn-outline-secondary btn-sm w-100" title="Clear">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </div>
</div>
</form>

{{-- Table toolbar --}}
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <span class="fw-semibold">
            <i class="bi bi-card-list me-1"></i>
            ID Records
            <span class="text-muted fw-normal">({{ number_format($records->total()) }} found)</span>
        </span>
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
            <a href="{{ route('exports.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-download me-1"></i>Export
            </a>
            @endcan
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="records-table">
            <thead class="table-light">
                <tr>
                    @can('bulkChangeStatus', \App\Models\IdRecord::class)
                    <th style="width:40px;">
                        <input type="checkbox" class="form-check-input" id="select-all" title="Select all">
                    </th>
                    @endcan
                    @php
                        $sortBy  = request('sort_by', 'name');
                        $sortDir = request('sort_dir', 'asc');
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
                    @can('bulkChangeStatus', \App\Models\IdRecord::class)
                    <td><input type="checkbox" class="form-check-input row-check" value="{{ $record->id }}"></td>
                    @endcan
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
                    <td colspan="8" class="text-center py-5 text-muted">
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
        <small class="text-muted">Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ number_format($records->total()) }}</small>
        {{ $records->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
    @endif
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
                    <p class="mb-3">
                        Employee: <strong id="modal-name"></strong>
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Status</label>
                        <div id="modal-current-status"></div>
                    </div>
                    <div class="mb-3">
                        <label for="modal-new-status" class="form-label fw-semibold">New Status <span class="text-danger">*</span></label>
                        <select name="status" id="modal-new-status" class="form-select" required>
                            @foreach(\App\Enums\IdStatus::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-1">
                        <label for="modal-remarks" class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" id="modal-remarks" class="form-control" rows="3" placeholder="Optional notes…"></textarea>
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
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Bulk Change Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Changing status for <strong id="bulk-count">0</strong> selected record(s).</p>
                    <div class="mb-3">
                        <label for="bulk-new-status" class="form-label fw-semibold">New Status <span class="text-danger">*</span></label>
                        <select name="status" id="bulk-new-status" class="form-select" required>
                            @foreach(\App\Enums\IdStatus::cases() as $s)
                            <option value="{{ $s->value }}">{{ $s->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-1">
                        <label for="bulk-remarks" class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" id="bulk-remarks" class="form-control" rows="3" placeholder="Optional notes for all selected records…"></textarea>
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
                    <h5 class="modal-title text-danger"><i class="bi bi-trash me-2"></i>Delete Record</h5>
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
const statusModal    = new bootstrap.Modal(document.getElementById('statusModal'));
const bulkModal      = document.getElementById('bulkStatusModal') ? new bootstrap.Modal(document.getElementById('bulkStatusModal')) : null;
const deleteModal    = new bootstrap.Modal(document.getElementById('deleteModal'));

// Single status change
document.querySelectorAll('.change-status-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const id = btn.dataset.recordId;
        document.getElementById('modal-name').textContent = btn.dataset.recordName;
        document.getElementById('modal-current-status').innerHTML = `<span class="badge bg-secondary badge-status">${btn.dataset.currentStatus}</span>`;
        document.getElementById('modal-new-status').value = btn.dataset.currentStatus;
        document.getElementById('modal-remarks').value = '';
        document.getElementById('status-form').action = `{{ rtrim(url('id-records'), '/') }}/${id}/status`;
        statusModal.show();
    });
});

// Bulk checkbox logic
const selectAll  = document.getElementById('select-all');
const rowChecks  = document.querySelectorAll('.row-check');
const bulkBtn    = document.getElementById('bulk-status-btn');

function updateBulkBtn() {
    const checked = document.querySelectorAll('.row-check:checked');
    if (bulkBtn) bulkBtn.disabled = checked.length === 0;
}

selectAll?.addEventListener('change', e => {
    rowChecks.forEach(cb => cb.checked = e.target.checked);
    updateBulkBtn();
});

rowChecks.forEach(cb => cb.addEventListener('change', updateBulkBtn));

bulkBtn?.addEventListener('click', () => {
    const checked = [...document.querySelectorAll('.row-check:checked')];
    document.getElementById('bulk-count').textContent = checked.length;
    const container = document.getElementById('bulk-ids-container');
    container.innerHTML = '';
    checked.forEach(cb => {
        const input = document.createElement('input');
        input.type  = 'hidden';
        input.name  = 'ids[]';
        input.value = cb.value;
        container.appendChild(input);
    });
    bulkModal?.show();
});

// Delete
document.querySelectorAll('.delete-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('delete-name').textContent = btn.dataset.recordName;
        document.getElementById('delete-form').action = `{{ rtrim(url('id-records'), '/') }}/${btn.dataset.recordId}`;
        deleteModal.show();
    });
});
</script>
@endpush
@endsection
