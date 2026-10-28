<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $from = Carbon::parse($request->query('from', now()->subDays(6)->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', now()->toDateString()))->endOfDay();
        $action = (string) $request->query('action');
        $userId = (string) $request->query('user');

        $logs = AuditLog::with('user')
            ->where('branch_id', $request->user()->branch_id)
            ->whereBetween('created_at', [$from, $to])
            ->when($action, fn ($q) => $q->where('action', $action))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'action' => $action,
            'userId' => $userId,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'counts' => AuditLog::where('branch_id', $request->user()->branch_id)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('action, COUNT(*) as n')->groupBy('action')->pluck('n', 'action'),
        ]);
    }
}
