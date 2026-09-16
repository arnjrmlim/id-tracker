@extends('layouts.app')
@section('title', 'Status History')
@section('page-title', 'Status History')

@section('content')

{{-- Filters --}}
<form method="GET" action="{{ route('history.index') }}">
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-semibold mb-1">Search Employee</label>
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Name or ID No…" value="{{ request('search') }}">
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
                <label class="form-label small fw-semibold mb-1">Changed By</label>
                <select name="changed_by" class="form-select form-select-sm">
                    <option value="">All Admins</option>
                    @foreach($admins as $admin)
                    <option value="{{ $admin->id }}" {{ request('changed_by') == $admin->id ? 'selected' : '' }}>{{ $admin->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from') }}">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold mb-1">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to') }}">
            </div>
            <div class="col-12 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('history.index') }}" class="btn btn-outline-secondary btn-sm w-100"><i class="bi bi-x-lg"></i></a>
            </div>
        </div>
    </div>
</div>
</form>

<div class="card shadow-sm">
    <div class="card-header">
        <i class="bi bi-clock-history text-primary me-2"></i>
        <strong>Status Change Log</strong>
        <span class="text-muted fw-normal">({{ number_format($histories->total()) }} entries)</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Date / Time</th>
                    <th>Employee</th>
                    <th>ID No</th>
                    <th>Old Status</th>
                    <th>New Status</th>
                    <th>Changed By</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse($histories as $h)
                <tr>
                    <td class="text-nowrap small text-muted">{{ $h->created_at->format('m/d/Y g:i A') }}</td>
                    <td>
                        @if($h->idRecord)
                        <a href="{{ route('id-records.show', $h->idRecord) }}" class="text-decoration-none">
                            {{ $h->idRecord->name }}
                        </a>
                        @else
                        <span class="text-muted fst-italic">Deleted</span>
                        @endif
                    </td>
                    <td class="fw-mono small">{{ $h->idRecord?->id_number ?? '—' }}</td>
                    <td>@include('partials.status-badge', ['status' => $h->old_status])</td>
                    <td>@include('partials.status-badge', ['status' => $h->new_status])</td>
                    <td class="small">{{ $h->changedBy?->name ?? '—' }}</td>
                    <td class="small text-muted" style="max-width:200px;">
                        {{ Str::limit($h->remarks, 60) }}
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-3 d-block mb-2"></i>No history records found.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($histories->hasPages())
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted">Showing {{ $histories->firstItem() }}–{{ $histories->lastItem() }} of {{ number_format($histories->total()) }}</small>
        {{ $histories->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
    @endif
</div>
@endsection
