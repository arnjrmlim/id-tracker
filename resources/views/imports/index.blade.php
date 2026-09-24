@extends('layouts.app')
@section('title', 'Import')
@section('page-title', 'Import ID Records')

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="bi bi-file-earmark-arrow-up text-primary me-2"></i>
                <strong>Upload Excel File</strong>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-4">
                    <h6 class="alert-heading"><i class="bi bi-info-circle me-1"></i>Before you import:</h6>
                    <ul class="mb-0 small">
                        <li>Use the exact template with headers: <code>NAME, POS, IDNO, DATEH, BDATE, ECON, IMG, SIGN, EMPLOYMENT TYPE</code></li>
                        <li>IDNO is used as the unique identifier — duplicate IDs will be updated, not duplicated.</li>
                        <li>Existing ID <strong>status will not be changed</strong> during an update import.</li>
                        <li>Date format: <code>MM/DD/YYYY</code></li>
                    </ul>
                </div>

                <form method="POST" action="{{ route('imports.preview') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="excel_file" class="form-label fw-semibold">Excel File <span class="text-danger">*</span></label>
                        <input type="file" id="excel_file" name="excel_file" class="form-control @error('excel_file') is-invalid @enderror"
                               accept=".xlsx,.xls,.csv" required>
                        <div class="form-text">Accepted: .xlsx, .xls, .csv — Max 10MB</div>
                        @error('excel_file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Preview Import
                        </button>
                        <a href="{{ route('imports.template') }}" class="btn btn-outline-secondary">
                            <i class="bi bi-download me-1"></i>Download Template
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="bi bi-table me-2 text-secondary"></i>
                <strong>Expected Excel Format</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered mb-0 small">
                        <thead class="table-dark">
                            <tr>
                                @foreach(['NAME','POS','IDNO','DATEH','BDATE','ECON','IMG','SIGN','EMPLOYMENT TYPE'] as $h)
                                <th>{{ $h }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Juan Dela Cruz</td>
                                <td>Staff</td>
                                <td>100001</td>
                                <td>01/15/2020</td>
                                <td>05/10/1990</td>
                                <td>Maria: 09171234567</td>
                                <td class="text-muted fst-italic">Z:\…\image.png</td>
                                <td class="text-muted fst-italic">Z:\…\sign.png</td>
                                <td>Employee</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
