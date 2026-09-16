<?php

namespace App\Http\Controllers;

use App\Enums\IdStatus;
use App\Models\IdStatusHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class IdStatusHistoryController extends Controller
{
    public function index(Request $request)
    {
        // Both administrators and ID Staff can view the status history
        Gate::authorize('staff-or-admin');

        $query = IdStatusHistory::with(['idRecord', 'changedBy'])
            ->orderByDesc('created_at');

        if ($search = $request->input('search')) {
            $query->whereHas('idRecord', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('id_number', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where(function ($q) use ($status) {
                $q->where('old_status', $status)->orWhere('new_status', $status);
            });
        }

        if ($adminId = $request->input('changed_by')) {
            $query->where('changed_by', $adminId);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $histories = $query->paginate(30)->withQueryString();
        $statuses  = IdStatus::cases();
        // Only show admin filter to admins — ID Staff sees the full list but can't filter by changer
        $admins    = auth()->user()->isAdmin()
            ? User::where('role', 'administrator')->orderBy('name')->get()
            : collect();

        return view('history.index', compact('histories', 'statuses', 'admins'));
    }
}
