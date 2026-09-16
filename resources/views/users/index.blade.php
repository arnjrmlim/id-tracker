@extends('layouts.app')
@section('title', 'Users')
@section('page-title', 'User Management')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center">
        <i class="bi bi-people-fill text-primary me-2"></i>
        <strong>Users</strong>
        <a href="{{ route('users.create') }}" class="ms-auto btn btn-success btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New User
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td class="fw-semibold">{{ $user->name }}</td>
                    <td><code>{{ $user->username }}</code></td>
                    <td class="small text-muted">{{ $user->email ?: '—' }}</td>
                    <td>
                        <span class="badge {{ $user->isAdmin() ? 'bg-primary' : 'bg-secondary' }}">
                            {{ $user->getRoleLabel() }}
                        </span>
                    </td>
                    <td>
                        @if($user->is_active)
                            <span class="badge bg-success">Active</span>
                        @else
                            <span class="badge bg-danger">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="btn-group btn-group-sm">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-secondary" title="Edit">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if($user->is_active)
                            <form method="POST" action="{{ route('users.deactivate', $user) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-warning" type="submit" title="Deactivate"
                                        {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                                    <i class="bi bi-person-x"></i>
                                </button>
                            </form>
                            @else
                            <form method="POST" action="{{ route('users.activate', $user) }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-outline-success" type="submit" title="Activate">
                                    <i class="bi bi-person-check"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">No users found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="card-footer">{{ $users->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
