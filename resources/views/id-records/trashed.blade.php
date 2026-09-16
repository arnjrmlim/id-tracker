@extends('layouts.app')
@section('title', 'Deleted Records')
@section('page-title', 'Deleted Records')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center">
        <i class="bi bi-trash text-danger me-2"></i>
        <strong>Soft-Deleted Records</strong>
        <a href="{{ route('id-records.index') }}" class="ms-auto btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>ID No</th>
                    <th>Name</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Deleted At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                <tr>
                    <td>{{ $record->id_number }}</td>
                    <td>{{ $record->name }}</td>
                    <td>{{ $record->position }}</td>
                    <td>@include('partials.status-badge', ['status' => $record->status])</td>
                    <td class="small text-muted">{{ $record->deleted_at->format('m/d/Y g:i A') }}</td>
                    <td>
                        <form method="POST" action="{{ route('id-records.restore', $record->id) }}">
                            @csrf @method('PATCH')
                            <button type="submit" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-arrow-counterclockwise me-1"></i>Restore
                            </button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="bi bi-check-circle fs-3 d-block mb-2"></i>No deleted records.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($records->hasPages())
    <div class="card-footer">{{ $records->withQueryString()->links('pagination::bootstrap-5') }}</div>
    @endif
</div>
@endsection
