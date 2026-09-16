@extends('layouts.app')
@section('title', 'Import Preview')
@section('page-title', 'Import Preview')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="bi bi-eye text-info me-2"></i>
                <strong>Import Preview</strong>
                <span class="text-muted fw-normal ms-2">{{ $originalName }}</span>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="fs-3 fw-bold text-primary">{{ $preview['total'] }}</div>
                            <div class="small text-muted">Total Rows</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="fs-3 fw-bold text-success">{{ $preview['new'] }}</div>
                            <div class="small text-muted">New Records</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="fs-3 fw-bold text-warning">{{ $preview['existing'] }}</div>
                            <div class="small text-muted">Will Update</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="border rounded p-3 text-center">
                            <div class="fs-3 fw-bold text-danger">{{ $preview['invalid'] }}</div>
                            <div class="small text-muted">Invalid Rows</div>
                        </div>
                    </div>
                </div>

                @if($preview['existing'] > 0)
                <div class="alert alert-warning small">
                    <i class="bi bi-shield-check me-1"></i>
                    <strong>{{ $preview['existing'] }} existing records</strong> will be updated.
                    Their current ID status will <strong>not</strong> be changed.
                </div>
                @endif

                <form method="POST" action="{{ route('imports.store') }}">
                    @csrf
                    <input type="hidden" name="tmp_path" value="{{ $tmpPath }}">
                    <input type="hidden" name="original_name" value="{{ $originalName }}">

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Import Mode</label>
                        @if($isIdStaff)
                        {{-- ID Staff is locked to add-only — cannot update existing records --}}
                        <input type="hidden" name="mode" value="add">
                        <div class="alert alert-info py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Add New Only</strong> — as ID Staff, existing records will be skipped automatically.
                            Only new IDNOs will be created.
                        </div>
                        @else
                        <div class="d-flex gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="mode-both" value="both" checked>
                                <label class="form-check-label" for="mode-both">
                                    <strong>Add &amp; Update</strong> — create new + update existing
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="mode-add" value="add">
                                <label class="form-check-label" for="mode-add">
                                    <strong>Add New Only</strong> — skip existing IDs
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="mode" id="mode-update" value="update">
                                <label class="form-check-label" for="mode-update">
                                    <strong>Update Existing Only</strong> — skip new IDs
                                </label>
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="d-flex gap-2">
                        <a href="{{ route('imports.index') }}" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back
                        </a>
                        <button type="submit" class="btn btn-primary" @if($preview['total'] === 0) disabled @endif>
                            <i class="bi bi-cloud-upload me-1"></i>Confirm Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
