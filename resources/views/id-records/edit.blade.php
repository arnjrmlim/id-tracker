@extends('layouts.app')
@section('title', 'Edit — ' . $idRecord->name)
@section('page-title', 'Edit ID Record')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-pencil text-warning me-2"></i>
                <strong>Edit: {{ $idRecord->name }}</strong>
                <a href="{{ route('id-records.show', $idRecord) }}" class="ms-auto btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye me-1"></i>View
                </a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('id-records.update', $idRecord) }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    @include('id-records.partials.form')
                    <div class="alert alert-info py-2 mt-3 small">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Note:</strong> Status can only be changed through the <em>Change Status</em> function. It cannot be modified here.
                    </div>
                    <hr>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('id-records.show', $idRecord) }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
