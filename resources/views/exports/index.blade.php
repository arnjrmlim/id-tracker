@extends('layouts.app')
@section('title', 'Export')
@section('page-title', 'Export Records')

@section('content')
<div class="row g-4">
    {{-- Standard Template Export --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <i class="bi bi-file-earmark-excel text-success me-2"></i>
                <strong>Standard Template Export</strong>
            </div>
            <div class="card-body">
                <p class="text-muted small">Downloads an Excel file with the original 8-column format:
                    <code>NAME, POS, IDNO, DATEH, BDATE, ECON, IMG, SIGN</code>
                </p>
                <form method="POST" action="{{ route('exports.template') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Filter by Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            @foreach($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Search (optional)</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Name, ID No, or Position…">
                    </div>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-download me-1"></i>Export Template
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- Tracker Report Export --}}
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <i class="bi bi-file-earmark-bar-graph text-primary me-2"></i>
                <strong>ID Tracker Report</strong>
            </div>
            <div class="card-body">
                <p class="text-muted small">Includes the STATUS column in addition to the standard 8 columns.
                    Useful for reporting and auditing.
                </p>
                <form method="POST" action="{{ route('exports.report') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Filter by Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            @foreach($statuses as $s)
                            <option value="{{ $s->value }}">{{ $s->value }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Search (optional)</label>
                        <input type="text" name="search" class="form-control form-control-sm"
                               placeholder="Name, ID No, or Position…">
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-download me-1"></i>Export Report
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
