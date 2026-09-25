@extends('layouts.app')
@section('title', 'My ID Requests')
@section('page-title', 'My ID Requests')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-list-check me-2"></i>
            My ID Requests
            <span class="text-muted fw-normal">({{ number_format($requests->total()) }} total)</span>
        </span>
    </div>

    <div class="card-body">
        {{-- Filters --}}
        <form method="GET" action="{{ route('id-requests.my-requests') }}" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search name, position..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="employment_type" class="form-select form-control-sm">
                    <option value="">All Employment Types</option>
                    @foreach(\App\Models\IdRecord::EMPLOYMENT_TYPES as $type)
                    <option value="{{ $type }}" {{ request('employment_type') === $type ? 'selected' : '' }}>
                        {{ $type }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="request_status" class="form-select form-control-sm">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('request_status') === 'pending' ? 'selected' : '' }}>Pending Approval</option>
                    <option value="approved" {{ request('request_status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('request_status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </div>
        </form>

        @if($requests->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Position</th>
                        <th>Employment Type</th>
                        <th>ID Number</th>
                        <th>Status</th>
                        <th>Requested At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $request)
                    <tr>
                        <td class="fw-semibold">{{ $request->name }}</td>
                        <td>{{ $request->position ?? '—' }}</td>
                        <td>
                            @if($request->employment_type)
                            <span class="badge {{ $request->employment_type === 'Agent' ? 'bg-info text-dark' : 'bg-primary' }}">
                                {{ $request->employment_type }}
                            </span>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($request->id_number)
                            <span class="fw-mono">{{ $request->id_number }}</span>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            @if($request->request_status)
                            <span class="badge {{ $request->request_status_badge_class }}">
                                {{ $request->request_status_enum ? $request->request_status_enum->label() : ucfirst($request->request_status) }}
                            </span>
                            @else
                            <span class="badge bg-secondary">Unknown</span>
                            @endif
                            @if($request->isRejected() && $request->rejection_reason)
                            <div class="small text-danger mt-1">{{ $request->rejection_reason }}</div>
                            @endif
                        </td>
                        <td>{{ $request->requested_at ? $request->requested_at->format('M d, Y') : '—' }}</td>
                        <td>
                            <a href="{{ route('id-requests.show', $request) }}" class="btn btn-sm btn-outline-primary" title="View Details">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($requests->hasPages())
        <div class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">
                Showing {{ $requests->firstItem() }}–{{ $requests->lastItem() }} of {{ number_format($requests->total()) }}
            </small>
            {{ $requests->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
        @endif

        @else
        <div class="text-center py-5 text-muted">
            <i class="bi bi-inbox fs-3 d-block mb-2"></i>
            No ID requests found.
            <a href="{{ route('id-records.create') }}" class="btn btn-primary btn-sm mt-2">
                <i class="bi bi-plus-circle me-1"></i>Submit New Request
            </a>
        </div>
        @endif
    </div>
</div>
@endsection