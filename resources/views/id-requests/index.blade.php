@extends('layouts.app')
@section('title', 'Pending ID Requests')
@section('page-title', 'Pending ID Requests')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-clock-history me-2"></i>
            Pending ID Requests
            <span class="text-muted fw-normal">({{ number_format($requests->total()) }} total)</span>
        </span>
    </div>

    <div class="card-body">
        {{-- Filters --}}
        <form method="GET" action="{{ route('id-requests.index') }}" class="row g-2 mb-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control form-control-sm"
                       placeholder="Search name, position..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="employment_type" class="form-select form-select-sm">
                    <option value="">All Employment Types</option>
                    @foreach(\App\Models\IdRecord::EMPLOYMENT_TYPES as $type)
                    <option value="{{ $type }}" {{ request('employment_type') === $type ? 'selected' : '' }}>
                        {{ $type }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </div>
            <div class="col-md-3">
                <a href="{{ route('id-requests.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-x-lg"></i> Clear
                </a>
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
                        <th>Requested By</th>
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
                            <span class="badge bg-info text-dark">
                                {{ $request->requester ? $request->requester->name : 'Unknown' }}
                            </span>
                        </td>
                        <td>{{ $request->requested_at ? $request->requested_at->format('M d, Y g:i A') : '—' }}</td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('id-requests.show', $request) }}" class="btn btn-outline-primary" title="Review">
                                    <i class="bi bi-eye"></i> Review
                                </a>
                            </div>
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
            No pending ID requests found.
        </div>
        @endif
    </div>
</div>
@endsection