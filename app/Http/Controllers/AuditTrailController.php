<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuditTrailController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAuditTrail', AuditLog::class);

        $query = AuditLog::with('user')->orderByDesc('created_at');

        // Search
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // Filter by action
        if ($request->filled('action') && $request->input('action') !== 'all') {
            $query->action($request->input('action'));
        }

        // Filter by module
        if ($request->filled('module') && $request->input('module') !== 'all') {
            $query->module($request->input('module'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->dateFrom($request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->dateTo($request->input('date_to'));
        }

        $logs = $query->paginate(25)->withQueryString();

        // Get available actions and modules for filters
        $actions = AuditLog::select('action')->distinct()->pluck('action')->sort();
        $modules = AuditLog::select('module')->distinct()->pluck('module')->sort();

        return view('audit-trail.index', compact('logs', 'actions', 'modules'));
    }

    public function show(AuditLog $auditLog)
    {
        Gate::authorize('viewAuditTrail', AuditLog::class);

        $auditLog->load('user');

        return view('audit-trail.show', compact('auditLog'));
    }
}
