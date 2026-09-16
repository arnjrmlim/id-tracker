@extends('layouts.app')
@section('title', 'Edit User')
@section('page-title', 'Edit User')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header">
                <i class="bi bi-person-gear text-warning me-2"></i>
                <strong>Edit: {{ $user->username }}</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('users.update', $user) }}">
                    @csrf @method('PUT')
                    @include('users.partials.form', ['user' => $user])
                    <hr>
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Reset Password Section --}}
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <i class="bi bi-key text-danger me-2"></i>
                <strong>Reset Password</strong>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('users.reset-password', $user) }}">
                    @csrf @method('PATCH')
                    <div class="mb-3">
                        <label for="reset_password" class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                        <input type="password" id="reset_password" name="password" class="form-control" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label for="reset_password_confirmation" class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" id="reset_password_confirmation" name="password_confirmation" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-danger btn-sm">
                        <i class="bi bi-key me-1"></i>Reset Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
