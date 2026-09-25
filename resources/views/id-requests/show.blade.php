@extends('layouts.app')
@section('title', 'Review ID Request')
@section('page-title', 'Review ID Request')

@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold">
                    <i class="bi bi-person-badge me-2"></i>
                    Request Details
                </span>
                @if($idRecord->request_status)
                <span class="badge {{ $idRecord->request_status_badge_class }}">
                    {{ $idRecord->request_status_enum?->label() ?? ucfirst($idRecord->request_status) }}
                </span>
                @else
                <span class="badge bg-secondary">Unknown</span>
                @endif
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Full Name</label>
                        <div class="fw-semibold">{{ $idRecord->name }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Position</label>
                        <div>{{ $idRecord->position ?? '—' }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Employment Type</label>
                        <div>
                            @if($idRecord->employment_type)
                            <span class="badge {{ $idRecord->employment_type === 'Agent' ? 'bg-info text-dark' : 'bg-primary' }}">
                                {{ $idRecord->employment_type }}
                            </span>
                            @else
                            <span class="text-muted">—</span>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Date Hired</label>
                        <div>{{ $idRecord->date_hired_formatted }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Birth Date</label>
                        <div>{{ $idRecord->birth_date_formatted }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Emergency Contact</label>
                        <div>{{ $idRecord->emergency_contact ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Images --}}
        <div class="card shadow-sm mb-3">
            <div class="card-header">
                <i class="bi bi-images me-2"></i>
                <strong>Images</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">ID Image</label>
                        @if($idRecord->hasImage())
                        <div class="text-center">
                            <img src="{{ route('id-records.image', $idRecord) }}"
                                 alt="ID Image" class="img-thumbnail" style="max-height: 200px;">
                        </div>
                        @else
                        <div class="text-center text-muted py-3 border rounded">
                            <i class="bi bi-image fs-4 d-block mb-1"></i>
                            No ID image
                        </div>
                        @endif
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Signature</label>
                        @if($idRecord->hasSignature())
                        <div class="text-center">
                            <img src="{{ route('id-records.signature', $idRecord) }}"
                                 alt="Signature" class="img-thumbnail" style="max-height: 200px;">
                        </div>
                        @else
                        <div class="text-center text-muted py-3 border rounded">
                            <i class="bi bi-pen fs-4 d-block mb-1"></i>
                            No signature
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Request Information --}}
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Request Information</strong>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Requested By</label>
                        <div>
                            <span class="badge bg-info text-dark">
                                {{ $idRecord->requester ? $idRecord->requester->name : 'Unknown' }}
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted">Requested At</label>
                        <div>{{ $idRecord->requested_at ? $idRecord->requested_at->format('M d, Y g:i A') : '—' }}</div>
                    </div>
                    @if($idRecord->isApproved())
                    <div class="col-12 mt-3">
                        <div class="alert alert-success mb-0">
                            <strong>Approved by {{ $idRecord->approver ? $idRecord->approver->name : 'Unknown' }}</strong> on {{ $idRecord->approved_at ? $idRecord->approved_at->format('M d, Y g:i A') : '—' }}
                            <div class="small text-muted mt-1">
                                ID Number assigned: <strong>{{ $idRecord->id_number }}</strong>
                            </div>
                        </div>
                    </div>
                    @endif
                    @if($idRecord->isRejected())
                    <div class="col-12 mt-3">
                        <div class="alert alert-danger mb-0">
                            <strong>Rejected:</strong> {{ $idRecord->rejection_reason }}
                            <div class="small text-muted mt-1">
                                Rejected by {{ $idRecord->rejecter ? $idRecord->rejecter->name : 'Unknown' }} on {{ $idRecord->rejected_at ? $idRecord->rejected_at->format('M d, Y g:i A') : '—' }}
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm sticky-top" style="top: 80px;">
            <div class="card-header">
                <i class="bi bi-check-circle me-2"></i>
                <strong>Actions</strong>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    @if($idRecord->isPendingApproval())
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                        <i class="bi bi-check-lg me-1"></i>Approve Request
                    </button>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                        <i class="bi bi-x-lg me-1"></i>Reject Request
                    </button>
                    @endif
                    <a href="{{ route('id-requests.index') }}" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Requests
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Approve Modal --}}
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('id-requests.approve', $idRecord) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-check-circle text-success me-2"></i>Approve ID Record
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Approving this request will assign an ID Number to <strong>{{ $idRecord->name }}</strong>.</p>
                    <div class="mb-3">
                        <label for="approve_id_number" class="form-label fw-semibold">
                            ID Number <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="approve_id_number" name="id_number"
                               class="form-control" required
                               placeholder="e.g. 200473">
                        <div class="form-text">Enter the ID Number to assign to this record.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-check-lg me-1"></i>Approve & Assign ID
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Reject Modal --}}
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('id-requests.reject', $idRecord) }}">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-x-circle text-danger me-2"></i>Reject ID Record Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Please provide a reason for rejecting this request.</p>
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label fw-semibold">
                            Rejection Reason <span class="text-danger">*</span>
                        </label>
                        <textarea id="rejection_reason" name="rejection_reason"
                                  class="form-control" rows="4" required
                                  placeholder="e.g. Duplicate employee record, missing information, etc."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-x-lg me-1"></i>Reject Request
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection