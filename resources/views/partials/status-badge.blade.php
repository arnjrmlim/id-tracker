@php
    $enum = \App\Enums\IdStatus::tryFrom($status ?? '');
    $badgeClass = $enum ? $enum->badgeClass() : 'bg-secondary';
@endphp
<span class="badge badge-status {{ $badgeClass }}">{{ $status ?? 'UNKNOWN' }}</span>
