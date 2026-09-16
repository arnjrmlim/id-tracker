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
                <form method="POST" action="{{ route('id-records.store') }}" enctype="multipart/form-data">
                    @csrf
                    @include('id-records.partials.form')
                    <hr>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('id-records.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-lg me-1"></i>Create Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
