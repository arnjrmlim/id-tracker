@extends('layouts.app')
@section('title', $user->name)
@section('page-title', 'User Detail')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-person-circle text-primary me-2"></i>
                <strong>{{ $user->name }}</strong>
                <a href="{{ route('users.edit', $user) }}" class="ms-auto btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-4 text-muted small text-uppercase">Username</dt>
                    <dd class="col-8"><code>{{ $user->username }}</code></dd>
                    <dt class="col-4 text-muted small text-uppercase">Email</dt>
                    <dd class="col-8">{{ $user->email ?: '—' }}</dd>
                    <dt class="col-4 text-muted small text-uppercase">Role</dt>
                    <dd class="col-8">
                        <span class="badge {{ $user->isAdmin() ? 'bg-primary' : 'bg-secondary' }}">{{ $user->getRoleLabel() }}</span>
                    </dd>
                    <dt class="col-4 text-muted small text-uppercase">Status</dt>
                    <dd class="col-8">
                        <span class="badge {{ $user->is_active ? 'bg-success' : 'bg-danger' }}">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </dd>
                    <dt class="col-4 text-muted small text-uppercase">Created</dt>
                    <dd class="col-8 small text-muted">{{ $user->created_at->format('m/d/Y') }}</dd>
                </dl>
            </div>
            <div class="card-footer">
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
