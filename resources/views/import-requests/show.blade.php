@extends('layouts.app')
@section('title', "Import Request #{$importRequest->id}")
@section('page-title', "Import Request #{$importRequest->id}")

@section('content')
<div class="card shadow-sm mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold">
            <i class="bi bi-file-earmark-excel me-2"></i>
            Import Request #{{ $importRequest->id }}
        </span>
        <span class="badge {{ $importRequest->getStatusBadgeClass() }} fs-6">
            {{ $importRequest->getStatusLabel() }}
        </span>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p class="mb-1"><strong>Requested By:</strong> {{ $importRequest->requester->name }}</p>
                <p class="mb-1"><strong>File Name:</strong> {{ $importRequest->file_name }}</p>
                <p class="mb-1"><strong>Submitted:</strong> {{ $importRequest->created_at->format('M d, Y g:i A') }}</p>
                <p class="mb-1"><strong>Mode:</strong> {{ ucfirst($importRequest->mode) }}</p>
            </div>
            <div class="col-md-6">
                <p class="mb-1"><strong>Total Rows:</strong> {{ $importRequest->total_rows }}</p>
                <p class="mb-1"><strong>Valid:</strong> {{ $importRequest->valid_rows }}</p>
                <p class="mb-1"><strong>Invalid:</strong> {{ $importRequest->invalid_rows }}</p>
                <p class="mb-1"><strong>New Records:</strong> {{ $importRequest->new_records }}</p>
                <p class="mb-1"><strong>Updates:</strong> {{ $importRequest->updates }}</p>
                <p class="mb-1"><strong>Skipped:</strong> {{ $importRequest->skipped }}</p>
            </div>
        </div>

        @if($importRequest->isApproved())
        <div class="alert alert-success mt-3">
            <strong>Approved By:</strong> {{ $importRequest->approver->name }}<br>
            <strong>Approved At:</strong> {{ $importRequest->approved_at->format('M d, Y g:i A') }}
        </div>
        @endif

        @if($importRequest->isRejected())
        <div class="alert alert-danger mt-3">
            <strong>Rejected By:</strong> {{ $importRequest->rejecter->name }}<br>
            <strong>Rejected At:</strong> {{ $importRequest->rejected_at->format('M d, Y g:i A') }}<br>
            <strong>Reason:</strong> {{ $importRequest->rejection_reason }}
        </div>
        @endif

        @if($importRequest->errors)
        <div class="alert alert-warning mt-3">
            <strong>Validation Errors:</strong>
            <pre class="mb-0 small">{{ $importRequest->errors }}</pre>
        </div>
        @endif
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header">
        <strong>Row Details</strong>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-sm">
                <thead class="table-light">
                    <tr>
                        <th>Row</th>
                        <th>Name</th>
                        <th>ID Number</th>
                        <th>Position</th>
                        <th>Employment Type</th>
                        <th>Action</th>
                        <th>Error</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($importRequest->rows as $row)
                    <tr>
                        <td>{{ $row->row_number }}</td>
                        <td>{{ $row->name }}</td>
                        <td class="fw-mono">{{ $row->id_number }}</td>
                        <td>{{ $row->position ?? '—' }}</td>
                        <td>{{ $row->employment_type ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $row->getActionBadgeClass() }}">
                                {{ $row->getActionLabel() }}
                            </span>
                        </td>
                        <td class="small text-muted">{{ $row->error_message ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@if($importRequest->isPending())
@can('approveImportRequest', $importRequest)
<div class="card shadow-sm mt-3">
    <div class="card-body">
        <form method="POST" action="{{ route('import-requests.approve', $importRequest) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-success"
                    onclick="return confirm('Are you sure you want to approve this import request? This will create {{ $importRequest->new_records }} and update {{ $importRequest->updates }} ID record(s).')">
                <i class="bi bi-check-circle me-1"></i> Approve Import
            </button>
        </form>

        <button type="button" class="btn btn-danger ms-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
            <i class="bi bi-x-circle me-1"></i> Reject
        </button>

        <a href="{{ route('import-requests.index') }}" class="btn btn-secondary ms-2">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>
@else
<div class="card shadow-sm mt-3">
    <div class="card-body">
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            This import request is pending Administrator approval. You cannot approve or reject this request.
        </div>
        <a href="{{ route('import-requests.index') }}" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>
</div>
@endcan
@else
<div class="mt-3">
    <a href="{{ route('import-requests.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back
    </a>
</div>
@endif

@can('approveImportRequest', $importRequest)
{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('import-requests.reject', $importRequest) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Import Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label fw-semibold">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" id="rejection_reason" class="form-control" rows="4" required
                                  placeholder="Please explain why this import request is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan
@endsection
