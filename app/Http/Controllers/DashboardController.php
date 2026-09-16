<?php

namespace App\Http\Controllers;

use App\Enums\IdStatus;
use App\Models\IdRecord;
use App\Models\IdStatusHistory;

class DashboardController extends Controller
{
    public function index()
    {
        $total = IdRecord::count();

        $statusCounts = [];
        foreach (IdStatus::cases() as $status) {
            $statusCounts[$status->value] = IdRecord::where('status', $status->value)->count();
        }

        $recentActivity = IdStatusHistory::with(['idRecord', 'changedBy'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return view('dashboard.index', compact('total', 'statusCounts', 'recentActivity'));
    }
}
