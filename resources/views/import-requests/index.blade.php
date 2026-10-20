@extends('layouts.app')
@section('title', 'Import Requests')
@section('page-title', 'Import Requests')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-file-earmark-excel me-2"></i>
            Import Requests
            <span class="text-muted fw-normal">({{ number_format($requests->total()) }} total)</span>
        </span>
    </div>

    <div class="card-body">
        {{-- Filters --}}
        <form method="GET" action="{{ route('import-requests.index') }}" class="row g-2 mb-3">
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </div>
            <div class="col-md-2">
                <a href="{{ route('import-requests.index') }}" class="btn btn-outline-secondary btn-sm w-100">
                    <i class="bi bi-x-lg"></i> Clear
                </a>
            </div>
        </form>

        @if($requests->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Request #</th>
                        <th>File Name</th>
                        <th>Requested By</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Summary</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($requests as $request)
                    <tr>
                        <td class="fw-mono">#{{ $request->id }}</td>
                        <td>
                            <span class="text-truncate d-inline-block" style="max-width: 200px;">
                                {{ $request->file_name }}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-info text-dark">
                                {{ $request->requester ? $request->requester->name : 'Unknown' }}
                            </span>
                        </td>
                        <td>{{ $request->created_at->format('M d, Y g:i A') }}</td>
                        <td>
                            <span class="badge {{ $request->getStatusBadgeClass() }}">
                                {{ $request->getStatusLabel() }}
                            </span>
                        </td>
                        <td class="small">
                            <div class="text-muted">
                                Total: {{ $request->total_rows }} |
                                Valid: {{ $request->valid_rows }} |
                                Invalid: {{ $request->invalid_rows }}
                            </div>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="{{ route('import-requests.show', $request) }}" class="btn btn-outline-primary" title="Review">
                                    <i class="bi bi-eye"></i> Review
                                </a>
                                @can('approveImportRequest', $request)
                                @if($request->isPending())
                                <form method="POST" action="{{ route('import-requests.approve', $request) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-success" title="Approve"
                                            onclick="return confirm('Are you sure you want to approve this import request? This will create/update ID records.')">
                                        <i class="bi bi-check-circle"></i>
                                    </button>
                                </form>
                                @endif
                                @endcan
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
            No import requests found.
        </div>
        @endif
    </div>
</div>
@endsection
