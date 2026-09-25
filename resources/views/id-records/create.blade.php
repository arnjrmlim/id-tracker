@extends('layouts.app')
@section('title', 'New ID Record')
@section('page-title', 'New ID Record')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="bi bi-plus-circle text-success me-2"></i>
                <strong>Create ID Record</strong>
            </div>
            <div class="card-body">
                @if(auth()->user()->isIdStaff())
                <div class="alert alert-info mb-3">
                    <i class="bi bi-info-circle me-2"></i>
                    <strong>Submit for Approval:</strong> This ID Record will be submitted for Admin approval. The ID Number will be assigned by an Admin after approval.
                </div>
                @endif

                <form method="POST" action="{{ route('id-records.store') }}" enctype="multipart/form-data">
                    @csrf
                    @include('id-records.partials.form', ['isIdStaff' => auth()->user()->isIdStaff()])
                    <hr>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('id-records.index') }}" class="btn btn-secondary">Cancel</a>
                        @if(auth()->user()->isIdStaff())
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-send me-1"></i>Submit for Approval
                        </button>
                        @else
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i>Create Record
                        </button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
