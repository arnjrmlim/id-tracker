@extends('layouts.app')
@section('title', 'Audit Log Details')
@section('page-title', 'Audit Log Details')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center">
        <i class="bi bi-journal-text text-primary me-2"></i>
        <strong>Audit Log Details</strong>
        <a href="{{ route('audit-trail.index') }}" class="ms-auto btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th class="bg-light" style="width: 30%;">Date & Time</th>
                        <td>{{ $auditLog->created_at->format('M d, Y H:i:s') }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Performed By</th>
                        <td>
                            @if($auditLog->user)
                                <strong>{{ $auditLog->user->name }}</strong> ({{ $auditLog->user->username }})
                            @else
                                <span class="text-muted">System</span>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="bg-light">Action</th>
                        <td><span class="badge bg-secondary">{{ $auditLog->action }}</span></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Module</th>
                        <td>{{ $auditLog->module }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">IP Address</th>
                        <td>{{ $auditLog->ip_address ?: '—' }}</td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th class="bg-light" style="width: 30%;">Target Type</th>
                        <td>{{ $auditLog->target_type ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Target ID</th>
                        <td>{{ $auditLog->target_id ?: '—' }}</td>
                    </tr>
                    <tr>
                        <th class="bg-light">Target Identifier</th>
                        <td>
                            @if($auditLog->target_identifier)
                                <code>{{ $auditLog->target_identifier }}</code>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <th class="bg-light">User Agent</th>
                        <td class="small text-muted">{{ $auditLog->user_agent ?: '—' }}</td>
                    </tr>
                </table>
            </div>
        </div>

        <hr>

        <h6 class="fw-bold mb-3">Description</h6>
        <p class="text-muted">{{ $auditLog->description }}</p>

        @if($auditLog->old_values || $auditLog->new_values)
        <hr>
        <h6 class="fw-bold mb-3">Changed Values</h6>
        <div class="table-responsive">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Field</th>
                        <th>Previous</th>
                        <th>New</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $allKeys = array_unique(array_merge(
                            array_keys($auditLog->old_values ?? []),
                            array_keys($auditLog->new_values ?? [])
                        ));
                    @endphp
                    @foreach($allKeys as $key)
                    @if(in_array($key, ['created_records', 'updated_records', 'failed_records', 'skipped_records']))
                    <tr>
                        <td class="fw-semibold">{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $key)) }}</td>
                        <td colspan="2">
                            @if(isset($auditLog->new_values[$key]) && is_array($auditLog->new_values[$key]))
                            <div class="small">
                                @foreach($auditLog->new_values[$key] as $record)
                                <div class="py-1">
                                    <strong>{{ $record['name'] }}</strong> — ID No. {{ $record['id_number'] }}
                                    @if(isset($record['error']))
                                    <span class="text-danger">— {{ $record['error'] }}</span>
                                    @elseif(isset($record['reason']))
                                    <span class="text-muted">— {{ $record['reason'] }}</span>
                                    @endif
                                </div>
                                @endforeach
                                @if(count($auditLog->new_values[$key]) >= 50)
                                <div class="text-muted fst-italic mt-2">Showing first 50 records of {{ count($auditLog->new_values[$key]) }}</div>
                                @endif
                            </div>
                            @else
                            —
                            @endif
                        </td>
                    </tr>
                    @else
                    <tr>
                        <td class="fw-semibold">{{ \Illuminate\Support\Str::title(str_replace('_', ' ', $key)) }}</td>
                        <td>{{ $auditLog->old_values[$key] ?? '—' }}</td>
                        <td>{{ $auditLog->new_values[$key] ?? '—' }}</td>
                    </tr>
                    @endif
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>
@endsection
