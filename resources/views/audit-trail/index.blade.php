@extends('layouts.app')
@section('title', 'Audit Trail')
@section('page-title', 'Audit Trail')

@section('content')
<div class="card shadow-sm">
    <div class="card-header">
        <div class="d-flex align-items-center">
            <i class="bi bi-journal-text text-primary me-2"></i>
            <strong>Audit Trail</strong>
        </div>
    </div>
    <div class="card-body">
        {{-- Filters --}}
        <form method="GET" action="{{ route('audit-trail.index') }}" class="row g-3 mb-4">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2">
                <select name="action" class="form-select form-select-sm">
                    <option value="all">All Actions</option>
                    @foreach($actions as $action)
                    <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="module" class="form-select form-select-sm">
                    <option value="all">All Modules</option>
                    @foreach($modules as $module)
                    <option value="{{ $module }}" {{ request('module') === $module ? 'selected' : '' }}>{{ $module }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control form-control-sm" placeholder="From" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control form-control-sm" placeholder="To" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-search"></i>
                </button>
            </div>
            @if(request()->hasAny(['search', 'action', 'module', 'date_from', 'date_to']))
            <div class="col-md-12">
                <a href="{{ route('audit-trail.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-x-circle"></i> Clear Filters
                </a>
            </div>
            @endif
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date & Time</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Module</th>
                        <th>Description</th>
                        <th>Target</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td class="small">{{ $log->created_at->format('M d, Y H:i') }}</td>
                        <td>
                            @if($log->user)
                                <span class="fw-semibold">{{ $log->user->username }}</span>
                            @else
                                <span class="text-muted">System</span>
                            @endif
                        </td>
                        <td><span class="badge bg-secondary">{{ $log->action }}</span></td>
                        <td>{{ $log->module }}</td>
                        <td>
                            <a href="{{ route('audit-trail.show', $log) }}" class="text-decoration-none">
                                {{ Str::limit($log->description, 80) }}
                            </a>
                        </td>
                        <td class="small">
                            @if($log->target_identifier)
                                <code>{{ $log->target_identifier }}</code>
                            @else
                                —
                            @endif
                        </td>
                        <td class="small text-muted">{{ $log->ip_address ?: '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No audit logs found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
        <div class="d-flex align-items-center justify-content-between mt-3">
            <div class="small text-muted">
                Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }} entries
            </div>
            {{ $logs->links('pagination::bootstrap-5') }}
        </div>
        @endif
    </div>
</div>
@endsection
