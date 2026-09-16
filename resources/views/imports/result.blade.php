@extends('layouts.app')
@section('title', 'Import Result')
@section('page-title', 'Import Complete')

@section('content')
@php
    // Split stored messages: validation errors vs business-rule skips.
    // Skipped messages are prefixed by the importer with "Row X: Existing IDNO"
    // or "Row X: New IDNO … skipped".
    $allMessages    = $log->errors ?? [];
    $errorMessages  = array_values(array_filter($allMessages, fn($m) => !str_contains($m, 'skipped')));
    $skipMessages   = array_values(array_filter($allMessages, fn($m) =>  str_contains($m, 'skipped')));
    $headerClass    = $log->failed_rows > 0 ? 'bg-warning text-dark' : 'bg-success text-white';
    $headerIcon     = $log->failed_rows > 0 ? 'exclamation-triangle' : 'check-circle';
@endphp
<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header {{ $headerClass }}">
                <i class="bi bi-{{ $headerIcon }} me-2"></i>
                <strong>Import Complete</strong>
            </div>
            <div class="card-body">
                <p class="text-muted mb-4">
                    <i class="bi bi-file-earmark-excel me-1"></i>
                    <strong>{{ $log->filename }}</strong>
                </p>

                {{-- Summary counts --}}
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-4">
                        <div class="border rounded p-3 text-center">
                            <div class="fs-2 fw-bold text-primary">{{ number_format($log->total_rows) }}</div>
                            <div class="small text-muted fw-semibold text-uppercase">Total Rows</div>
                            <div class="text-muted" style="font-size:.7rem;">Meaningful rows processed</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="border rounded p-3 text-center">
                            <div class="fs-2 fw-bold text-success">{{ number_format($log->created_rows) }}</div>
                            <div class="small text-muted fw-semibold text-uppercase">Created</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="border rounded p-3 text-center">
                            <div class="fs-2 fw-bold text-info">{{ number_format($log->updated_rows) }}</div>
                            <div class="small text-muted fw-semibold text-uppercase">Updated</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="border rounded p-3 text-center">
                            <div class="fs-2 fw-bold text-secondary">{{ number_format($log->skipped_rows) }}</div>
                            <div class="small text-muted fw-semibold text-uppercase">Skipped</div>
                            <div class="text-muted" style="font-size:.7rem;">Business-rule skips</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="border rounded p-3 text-center {{ $log->failed_rows > 0 ? 'border-danger' : '' }}">
                            <div class="fs-2 fw-bold {{ $log->failed_rows > 0 ? 'text-danger' : 'text-muted' }}">
                                {{ number_format($log->failed_rows) }}
                            </div>
                            <div class="small text-muted fw-semibold text-uppercase">Failed</div>
                            <div class="text-muted" style="font-size:.7rem;">Validation errors</div>
                        </div>
                    </div>
                </div>

                {{-- Validation errors --}}
                <div class="card border-{{ count($errorMessages) > 0 ? 'danger' : 'light' }} mb-3">
                    <div class="card-header {{ count($errorMessages) > 0 ? 'text-danger' : 'text-muted' }} fw-semibold py-2">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Errors ({{ count($errorMessages) }})
                    </div>
                    @if(count($errorMessages) > 0)
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush small">
                            @foreach($errorMessages as $msg)
                            <li class="list-group-item py-2 text-danger">
                                <i class="bi bi-x-circle me-1"></i>{{ $msg }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @else
                    <div class="card-body py-2 small text-muted">
                        <i class="bi bi-check-circle me-1 text-success"></i>No errors.
                    </div>
                    @endif
                </div>

                {{-- Skipped rows (business-rule, not errors) --}}
                @if(count($skipMessages) > 0)
                <div class="card border-secondary mb-3">
                    <div class="card-header text-secondary fw-semibold py-2">
                        <i class="bi bi-skip-forward me-1"></i>
                        Skipped ({{ count($skipMessages) }})
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush small">
                            @foreach($skipMessages as $msg)
                            <li class="list-group-item py-2 text-secondary">
                                <i class="bi bi-dash-circle me-1"></i>{{ $msg }}
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
                @endif

                <div class="d-flex gap-2 mt-3">
                    <a href="{{ route('id-records.index') }}" class="btn btn-primary">
                        <i class="bi bi-card-list me-1"></i>View ID Records
                    </a>
                    <a href="{{ route('imports.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-upload me-1"></i>Import Another
                    </a>
                </div>
            </div>
            <div class="card-footer small text-muted">
                Imported by {{ $log->importedBy?->name ?? '—' }}
                on {{ $log->created_at->format('m/d/Y g:i A') }}
            </div>
        </div>
    </div>
</div>
@endsection
