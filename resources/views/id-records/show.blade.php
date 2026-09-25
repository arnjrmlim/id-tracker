@extends('layouts.app')
@section('title', $idRecord->name)
@section('page-title', 'ID Record Detail')

@section('content')
<div class="row g-4">
    {{-- Main info --}}
    <div class="col-lg-8">
        {{-- Approval Information --}}
        @if($idRecord->request_status)
        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-info-circle text-primary"></i>
                <strong>Approval Information</strong>
                <span class="badge {{ $idRecord->request_status_badge_class }} ms-auto">
                    {{ $idRecord->request_status_enum ? $idRecord->request_status_enum->label() : ucfirst($idRecord->request_status) }}
                </span>
            </div>
            <div class="card-body">
                @if($idRecord->isPendingApproval())
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-hourglass-split me-2"></i>
                    <strong>Pending Approval:</strong> This record is awaiting Admin approval.
                    <div class="small text-muted mt-1">
                        Requested by {{ $idRecord->requester ? $idRecord->requester->name : 'Unknown' }} on {{ $idRecord->requested_at ? $idRecord->requested_at->format('M d, Y g:i A') : '—' }}
                    </div>
                </div>
                @elseif($idRecord->isApproved())
                <div class="alert alert-success mb-0">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Approved:</strong> This record has been approved.
                    <div class="small text-muted mt-1">
                        Requested by {{ $idRecord->requester ? $idRecord->requester->name : 'Unknown' }} on {{ $idRecord->requested_at ? $idRecord->requested_at->format('M d, Y g:i A') : '—' }}
                    </div>
                    <div class="small text-muted">
                        Approved by {{ $idRecord->approver ? $idRecord->approver->name : 'Unknown' }} on {{ $idRecord->approved_at ? $idRecord->approved_at->format('M d, Y g:i A') : '—' }}
                    </div>
                </div>
                @elseif($idRecord->isRejected())
                <div class="alert alert-danger mb-0">
                    <i class="bi bi-x-circle me-2"></i>
                    <strong>Rejected:</strong> {{ $idRecord->rejection_reason }}
                    <div class="small text-muted mt-1">
                        Requested by {{ $idRecord->requester ? $idRecord->requester->name : 'Unknown' }} on {{ $idRecord->requested_at ? $idRecord->requested_at->format('M d, Y g:i A') : '—' }}
                    </div>
                    <div class="small text-muted">
                        Rejected by {{ $idRecord->rejecter ? $idRecord->rejecter->name : 'Unknown' }} on {{ $idRecord->rejected_at ? $idRecord->rejected_at->format('M d, Y g:i A') : '—' }}
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-person-badge text-primary"></i>
                <strong>Employee Information</strong>
                @can('update', $idRecord)
                <a href="{{ route('id-records.edit', $idRecord) }}" class="ms-auto btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
                @endcan
            </div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4 text-muted small text-uppercase">Name</dt>
                    <dd class="col-sm-8 fw-semibold fs-5">{{ $idRecord->name }}</dd>

                    <dt class="col-sm-4 text-muted small text-uppercase">Position</dt>
                    <dd class="col-sm-8">{{ $idRecord->position ?: '—' }}</dd>

                    <dt class="col-sm-4 text-muted small text-uppercase">ID Number</dt>
                    <dd class="col-sm-8 fw-mono fw-bold text-primary">{{ $idRecord->id_number }}</dd>

                    <dt class="col-sm-4 text-muted small text-uppercase">Date Hired</dt>
                    <dd class="col-sm-8">{{ $idRecord->date_hired_formatted ?: '—' }}</dd>

                    <dt class="col-sm-4 text-muted small text-uppercase">Birth Date</dt>
                    <dd class="col-sm-8">{{ $idRecord->birth_date_formatted ?: '—' }}</dd>

                    <dt class="col-sm-4 text-muted small text-uppercase">Emergency Contact</dt>
                    <dd class="col-sm-8">{{ $idRecord->emergency_contact ?: '—' }}</dd>
                </dl>
            </div>
        </div>

        {{-- File Paths --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <i class="bi bi-images text-secondary me-2"></i>
                <strong>Files</strong>
            </div>
            <div class="card-body">

                {{-- ── ID IMAGE ── --}}
                <div class="mb-4">
                    <label class="form-label small text-muted text-uppercase fw-semibold d-flex align-items-center gap-2">
                        <i class="bi bi-person-badge"></i> ID Image
                    </label>

                    @if($idRecord->image_source === 'upload' && $idRecord->image_upload_path)
                        {{-- Uploaded file ── served from Laravel storage --}}
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-info text-dark"><i class="bi bi-upload me-1"></i>Uploaded File</span>
                        </div>
                        <div class="image-wrap">
                            <img src="{{ route('id-records.image', $idRecord) }}"
                                 alt="ID Image"
                                 class="img-thumbnail d-block"
                                 style="max-height:200px; max-width:100%;"
                                 onerror="this.closest('.image-wrap').querySelector('.img-unavailable').classList.remove('d-none'); this.classList.add('d-none');">
                            <div class="img-unavailable d-none alert alert-light border py-2 small mt-1">
                                <i class="bi bi-exclamation-circle me-1 text-warning"></i>
                                Uploaded image unavailable.
                            </div>
                        </div>

                    @elseif($idRecord->image_source === 'network' && $idRecord->image_path)
                        {{-- Network / local path ── streamed via controller --}}
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-secondary"><i class="bi bi-hdd-network me-1"></i>Network Path</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 copy-path-btn"
                                    data-path="{{ addslashes($idRecord->image_path) }}"
                                    title="Copy path to clipboard">
                                <i class="bi bi-clipboard me-1"></i>Copy Path
                            </button>
                        </div>
                        <code class="small bg-light p-2 rounded d-block mb-2">{{ $idRecord->image_path }}</code>
                        <div class="image-wrap">
                            <img src="{{ route('id-records.image', $idRecord) }}"
                                 alt="ID Image"
                                 class="img-thumbnail d-block"
                                 style="max-height:200px; max-width:100%;"
                                 onerror="this.closest('.image-wrap').querySelector('.img-unavailable').classList.remove('d-none'); this.classList.add('d-none');">
                            <div class="img-unavailable d-none alert alert-light border py-2 small mt-1">
                                <i class="bi bi-info-circle me-1 text-info"></i>
                                Network image is currently unavailable — the file may not be accessible from this machine.
                            </div>
                        </div>

                    @else
                        <span class="text-muted small">Not provided</span>
                    @endif
                </div>

                <hr class="my-3">

                {{-- ── SIGNATURE ── --}}
                <div>
                    <label class="form-label small text-muted text-uppercase fw-semibold d-flex align-items-center gap-2">
                        <i class="bi bi-pen"></i> Signature Image
                    </label>

                    @if($idRecord->signature_source === 'upload' && $idRecord->signature_upload_path)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-info text-dark"><i class="bi bi-upload me-1"></i>Uploaded File</span>
                        </div>
                        <div class="image-wrap">
                            <img src="{{ route('id-records.signature', $idRecord) }}"
                                 alt="Signature"
                                 class="img-thumbnail d-block"
                                 style="max-height:100px; max-width:100%;"
                                 onerror="this.closest('.image-wrap').querySelector('.img-unavailable').classList.remove('d-none'); this.classList.add('d-none');">
                            <div class="img-unavailable d-none alert alert-light border py-2 small mt-1">
                                <i class="bi bi-exclamation-circle me-1 text-warning"></i>
                                Uploaded signature unavailable.
                            </div>
                        </div>

                    @elseif($idRecord->signature_source === 'network' && $idRecord->signature_path)
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-secondary"><i class="bi bi-hdd-network me-1"></i>Network Path</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 copy-path-btn"
                                    data-path="{{ addslashes($idRecord->signature_path) }}"
                                    title="Copy path to clipboard">
                                <i class="bi bi-clipboard me-1"></i>Copy Path
                            </button>
                        </div>
                        <code class="small bg-light p-2 rounded d-block mb-2">{{ $idRecord->signature_path }}</code>
                        <div class="image-wrap">
                            <img src="{{ route('id-records.signature', $idRecord) }}"
                                 alt="Signature"
                                 class="img-thumbnail d-block"
                                 style="max-height:100px; max-width:100%;"
                                 onerror="this.closest('.image-wrap').querySelector('.img-unavailable').classList.remove('d-none'); this.classList.add('d-none');">
                            <div class="img-unavailable d-none alert alert-light border py-2 small mt-1">
                                <i class="bi bi-info-circle me-1 text-info"></i>
                                Network image is currently unavailable — the file may not be accessible from this machine.
                            </div>
                        </div>

                    @else
                        <span class="text-muted small">Not provided</span>
                    @endif
                </div>

            </div>
        </div>
    </div>

    {{-- Status panel --}}
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <i class="bi bi-info-circle text-primary me-2"></i>
                <strong>ID Status</strong>
            </div>
            <div class="card-body text-center py-4">
                @include('partials.status-badge', ['status' => $idRecord->status])
                <div class="mt-2 text-muted small">Current Status</div>

                @can('changeStatus', $idRecord)
                <hr>
                <button type="button" class="btn btn-warning w-100" id="change-status-btn">
                    <i class="bi bi-arrow-repeat me-1"></i>Change Status
                </button>
                @endcan
            </div>
            <div class="card-footer text-muted small">
                <i class="bi bi-calendar3 me-1"></i>Created: {{ $idRecord->created_at->format('m/d/Y g:i A') }}<br>
                <i class="bi bi-pencil me-1"></i>Updated: {{ $idRecord->updated_at->format('m/d/Y g:i A') }}
            </div>
        </div>

        {{-- Back / Delete --}}
        <div class="d-grid gap-2">
            <a href="{{ route('id-records.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to List
            </a>
            @can('delete', $idRecord)
            <button type="button" class="btn btn-outline-danger btn-sm" id="delete-btn">
                <i class="bi bi-trash me-1"></i>Delete Record
            </button>
            @endcan
        </div>
    </div>

    {{-- Status History --}}
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="bi bi-clock-history text-primary me-2"></i>
                <strong>Status History</strong>
            </div>
            @if($idRecord->statusHistories->isEmpty())
            <div class="card-body text-center text-muted py-4">
                <i class="bi bi-clock fs-3 d-block mb-2"></i>No status changes recorded.
            </div>
            @else
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Status Changed Date</th>
                            <th>Effective Status Date</th>
                            <th>Old Status</th>
                            <th>New Status</th>
                            <th>Changed By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($idRecord->statusHistories as $h)
                        <tr>
                            <td class="text-nowrap small text-muted">
                                {{ $h->created_at->format('m/d/Y g:i A') }}
                            </td>
                            <td class="text-nowrap small">
                                @if($h->effective_status_date)
                                    <span class="{{ $h->effective_status_date->lt($h->created_at->startOfDay()) ? 'text-warning fw-semibold' : 'text-muted' }}">
                                        {{ $h->effective_status_date->format('m/d/Y') }}
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>@include('partials.status-badge', ['status' => $h->old_status])</td>
                            <td>@include('partials.status-badge', ['status' => $h->new_status])</td>
                            <td class="small">{{ $h->changedBy?->name ?? '—' }}</td>
                            <td class="small text-muted">{{ $h->remarks ?: '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Status Change Modal --}}
@can('changeStatus', $idRecord)
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('id-records.status.update', $idRecord) }}">
            @csrf @method('PATCH')
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat me-2"></i>Change ID Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Employee: <strong>{{ $idRecord->name }}</strong></p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Current Status</label>
                        <div>@include('partials.status-badge', ['status' => $idRecord->status])</div>
                    </div>
                    <div class="mb-3">
                        <label for="new-status" class="form-label fw-semibold">New Status <span class="text-danger">*</span></label>
                        <select name="status" id="new-status" class="form-select" required>
                            @foreach(\App\Enums\IdStatus::cases() as $s)
                            <option value="{{ $s->value }}" {{ $idRecord->status === $s->value ? 'selected' : '' }}>{{ $s->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="remarks" class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" id="remarks" class="form-control" rows="3" placeholder="Optional notes about this status change…"></textarea>
                    </div>
                    <div class="mt-3">
                        <label for="effective_status_date" class="form-label fw-semibold">
                            Effective Status Date
                        </label>
                        <input type="date" name="effective_status_date" id="effective_status_date"
                               class="form-control"
                               value="{{ date('Y-m-d') }}">
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            The date when this status should officially take effect.
                            Use this when recording a status change after the actual effective date.
                        </div>
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
@endcan

{{-- Delete Modal --}}
@can('delete', $idRecord)
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form method="POST" action="{{ route('id-records.destroy', $idRecord) }}">
            @csrf @method('DELETE')
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title text-danger"><i class="bi bi-trash me-2"></i>Delete Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body pt-0">
                    Delete <strong>{{ $idRecord->name }}</strong>?
                    <br><small class="text-muted">Soft delete — can be restored.</small>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endcan

@push('scripts')
<script>
    @can('changeStatus', $idRecord)
    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
    document.getElementById('change-status-btn')?.addEventListener('click', () => statusModal.show());
    @endcan

    @can('delete', $idRecord)
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    document.getElementById('delete-btn')?.addEventListener('click', () => deleteModal.show());
    @endcan

    // ── Copy Path — works on HTTP (LAN) and HTTPS ─────────────────────────────
    // navigator.clipboard requires a secure context (HTTPS / localhost).
    // When accessed over plain HTTP on the local network, fall back to the
    // legacy execCommand('copy') approach via a temporary textarea.
    function copyPathToClipboard(btn) {
        const path = btn.dataset.path;
        const originalHtml = btn.innerHTML;

        function markSuccess() {
            btn.innerHTML = '<i class="bi bi-clipboard-check me-1"></i>Copied!';
            setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
        }

        function markFailure() {
            btn.innerHTML = '<i class="bi bi-clipboard-x me-1"></i>Failed';
            setTimeout(() => { btn.innerHTML = originalHtml; }, 2000);
        }

        // Try the modern Clipboard API first (HTTPS / localhost)
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(path).then(markSuccess).catch(() => {
                legacyCopy(path) ? markSuccess() : markFailure();
            });
            return;
        }

        // Fallback for plain HTTP (LAN access)
        legacyCopy(path) ? markSuccess() : markFailure();
    }

    function legacyCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.style.cssText = 'position:fixed;top:-9999px;left:-9999px;opacity:0;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        let ok = false;
        try { ok = document.execCommand('copy'); } catch (_) {}
        document.body.removeChild(ta);
        return ok;
    }

    document.querySelectorAll('.copy-path-btn').forEach(btn => {
        btn.addEventListener('click', () => copyPathToClipboard(btn));
    });
</script>
@endpush
@endsection
