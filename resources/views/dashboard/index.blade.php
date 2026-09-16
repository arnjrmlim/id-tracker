@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
    {{-- Total card --}}
    <div class="col-12 col-sm-6 col-xl-3">
        <a href="{{ route('id-records.index') }}" class="text-decoration-none">
            <div class="stat-card card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase small opacity-75 mb-1">Total IDs</div>
                            <div class="stat-number">{{ number_format($total) }}</div>
                        </div>
                        <i class="bi bi-collection-fill fs-1 opacity-50"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>

    @foreach(\App\Enums\IdStatus::cases() as $statusEnum)
    @php
        $count = $statusCounts[$statusEnum->value] ?? 0;
        $colorMap = [
            'PENDING'        => 'secondary',
            'FOR PROCESSING' => 'warning',
            'READY'          => 'info',
            'RELEASED'       => 'success',
            'LOST'           => 'danger',
            'DAMAGED'        => 'orange',
            'CANCELLED'      => 'dark',
        ];
        $color = $colorMap[$statusEnum->value] ?? 'secondary';
    @endphp
    <div class="col-12 col-sm-6 col-xl-3">
        <a href="{{ route('id-records.index', ['status' => $statusEnum->value]) }}" class="text-decoration-none">
            <div class="stat-card card h-100" style="border-left: 4px solid var(--bs-{{ $color === 'orange' ? 'warning' : $color }});">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="text-uppercase small text-muted mb-1">{{ $statusEnum->value }}</div>
                            <div class="stat-number text-{{ $color === 'orange' ? 'warning' : $color }}">{{ number_format($count) }}</div>
                        </div>
                        @php
                            $iconMap = [
                                'PENDING'        => 'hourglass-split',
                                'FOR PROCESSING' => 'gear-fill',
                                'READY'          => 'check2-circle',
                                'RELEASED'       => 'bag-check-fill',
                                'LOST'           => 'question-circle-fill',
                                'DAMAGED'        => 'exclamation-triangle-fill',
                                'CANCELLED'      => 'x-circle-fill',
                            ];
                        @endphp
                        <i class="bi bi-{{ $iconMap[$statusEnum->value] ?? 'circle' }} fs-2 text-{{ $color === 'orange' ? 'warning' : $color }} opacity-50"></i>
                    </div>
                </div>
            </div>
        </a>
    </div>
    @endforeach
</div>

{{-- Recent Activity --}}
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="bi bi-activity text-primary"></i>
        <strong>Recent ID Activity</strong>
        <a href="{{ route('history.index') }}" class="ms-auto btn btn-sm btn-outline-primary">
            View All <i class="bi bi-arrow-right"></i>
        </a>
    </div>
    <div class="card-body p-0">
        @if($recentActivity->isEmpty())
            <div class="p-4 text-center text-muted">
                <i class="bi bi-inbox fs-3 d-block mb-2"></i>
                No status changes recorded yet.
            </div>
        @else
        <div class="list-group list-group-flush">
            @foreach($recentActivity as $activity)
            <div class="list-group-item list-group-item-action px-4 py-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold">
                            {{ $activity->idRecord?->name ?? 'Deleted Record' }}
                            <small class="text-muted ms-1">IDNO: {{ $activity->idRecord?->id_number ?? '—' }}</small>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            @include('partials.status-badge', ['status' => $activity->old_status])
                            <i class="bi bi-arrow-right text-muted small"></i>
                            @include('partials.status-badge', ['status' => $activity->new_status])
                        </div>
                        @if($activity->remarks)
                        <small class="text-muted fst-italic">{{ Str::limit($activity->remarks, 80) }}</small>
                        @endif
                    </div>
                    <div class="text-end ms-3 flex-shrink-0">
                        <small class="text-muted d-block">{{ $activity->changedBy?->name ?? 'Unknown' }}</small>
                        <small class="text-muted">{{ $activity->created_at->diffForHumans() }}</small>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@endsection
