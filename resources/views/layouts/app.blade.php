<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ID Tracker') — {{ config('app.name') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            --sidebar-width: 240px;
            --sidebar-bg: #1a3c5e;
            --sidebar-active: #0d6efd;
            --topbar-height: 56px;
        }
        body { background: #f4f6f9; font-size: .9rem; }

        /* ── Sidebar ── */
        #sidebar {
            width: var(--sidebar-width);
            min-height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            top: 0; left: 0;
            z-index: 1000;
            display: flex;
            flex-direction: column;
            transition: transform .25s ease;
        }
        #sidebar .sidebar-brand {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,.1);
            color: #fff;
            font-weight: 700;
            font-size: 1.1rem;
            text-decoration: none;
            display: flex; align-items: center; gap: .5rem;
        }
        #sidebar .sidebar-brand i { font-size: 1.4rem; color: #5dade2; }
        #sidebar .nav-link {
            color: rgba(255,255,255,.75);
            padding: .6rem 1.25rem;
            border-radius: 0;
            display: flex; align-items: center; gap: .65rem;
            font-size: .875rem;
            transition: background .15s, color .15s;
        }
        #sidebar .nav-link:hover,
        #sidebar .nav-link.active {
            background: rgba(255,255,255,.12);
            color: #fff;
        }
        #sidebar .nav-link.active { border-left: 3px solid var(--sidebar-active); }
        #sidebar .sidebar-section {
            font-size: .7rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(255,255,255,.4);
            padding: .75rem 1.25rem .25rem;
        }

        /* ── Top bar ── */
        #topbar {
            margin-left: var(--sidebar-width);
            height: var(--topbar-height);
            background: #fff;
            border-bottom: 1px solid #dee2e6;
            display: flex; align-items: center;
            padding: 0 1.25rem;
            position: sticky; top: 0; z-index: 999;
        }

        /* ── Main content ── */
        #main-content {
            margin-left: var(--sidebar-width);
            padding: 1.5rem;
            min-height: calc(100vh - var(--topbar-height));
        }

        /* ── Status badges ── */
        .badge-status { font-size: .75rem; padding: .35em .65em; letter-spacing: .03em; }
        .bg-orange { background-color: #fd7e14 !important; }

        /* ── Table ── */
        .table-hover tbody tr:hover { background: #eef5ff; }
        .table th { font-size: .78rem; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }

        /* ── Card stats ── */
        .stat-card { border: none; border-radius: .5rem; transition: transform .15s, box-shadow .15s; cursor: pointer; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1.5rem rgba(0,0,0,.1); }
        .stat-card .stat-number { font-size: 2rem; font-weight: 700; }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.show { transform: translateX(0); }
            #topbar, #main-content { margin-left: 0; }
        }
    </style>

    @stack('styles')
</head>
<body>

<!-- ── Sidebar ── -->
<nav id="sidebar">
    <a class="sidebar-brand" href="{{ route('dashboard') }}">
        <img src="{{ asset('images/logo.png') }}" alt="Logo" style="height:60px; width:60px; object-fit:contain;">
        ID Tracker
    </a>

    @php $u = auth()->user(); @endphp
    <ul class="nav flex-column mt-2">

        {{-- Dashboard: all roles --}}
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>

        <li><span class="sidebar-section">Records</span></li>

        {{-- ID Tracker list: all roles --}}
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('id-records.index') ? 'active' : '' }}" href="{{ route('id-records.index') }}">
                <i class="bi bi-card-list"></i> ID Tracker
            </a>
        </li>

        {{-- Add New ID: admin + id_staff --}}
        @if($u->isAdmin() || $u->isIdStaff())
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('id-records.create') ? 'active' : '' }}" href="{{ route('id-records.create') }}">
                <i class="bi bi-plus-circle"></i> Add New ID
            </a>
        </li>
        @endif

        {{-- Status History: admin + id_staff (+ regular user optionally) --}}
        @if($u->isAdmin() || $u->isIdStaff())
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('history.*') ? 'active' : '' }}" href="{{ route('history.index') }}">
                <i class="bi bi-clock-history"></i> Status History
            </a>
        </li>
        @endif

        {{-- Data section: admin + id_staff only --}}
        @if($u->isAdmin() || $u->isIdStaff())
        <li><span class="sidebar-section">Data</span></li>

        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}" href="{{ route('imports.index') }}">
                <i class="bi bi-file-earmark-arrow-up"></i> Import
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('exports.*') ? 'active' : '' }}" href="{{ route('exports.index') }}">
                <i class="bi bi-file-earmark-arrow-down"></i> Export
            </a>
        </li>
        @endif

        {{-- Admin section: administrator only --}}
        @if($u->isAdmin())
        <li><span class="sidebar-section">Admin</span></li>

        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                <i class="bi bi-people-fill"></i> Users
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.backup.index') }}">
                <i class="bi bi-gear-fill"></i> Settings
            </a>
        </li>
        @endif

    </ul>

    <div class="mt-auto p-3 border-top border-secondary-subtle">
        <small class="text-white-50">v1.0.0</small>
    </div>
</nav>

<!-- ── Top bar ── -->
<div id="topbar">
    <button class="btn btn-sm btn-outline-secondary d-md-none me-2" id="sidebar-toggle">
        <i class="bi bi-list"></i>
    </button>

    <span class="fw-semibold text-secondary flex-grow-1">@yield('page-title', '')</span>

    <div class="dropdown ms-auto">
        <button class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle fs-5"></i>
            <span>{{ auth()->user()->name }}</span>
            <span class="badge {{ auth()->user()->getRoleBadgeClass() }} ms-1" style="font-size:.65rem;">
                {{ auth()->user()->getRoleLabel() }}
            </span>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><h6 class="dropdown-header">{{ auth()->user()->username }}</h6></li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item text-danger" type="submit">
                        <i class="bi bi-box-arrow-right me-1"></i> Logout
                    </button>
                </form>
            </li>
        </ul>
    </div>
</div>

<!-- ── Main content ── -->
<main id="main-content">
    @include('partials.alerts')
    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Mobile sidebar toggle
    document.getElementById('sidebar-toggle')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('show');
    });
</script>
@stack('scripts')
</body>
</html>
