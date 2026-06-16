<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            abort_unless(auth()->user()?->is_admin, 403, 'Access denied. Admins only.');

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $filters = [
            'action' => $request->string('action')->trim()->value(),
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
        ];

        // Server-side filtered; the VibeDataTable paginates the capped subset.
        $logs = AuditLog::with('user:id,name')
            ->when($filters['action'] !== '', fn ($q) => $q->where('action', 'like', '%'.$filters['action'].'%'))
            ->when($filters['from'], fn ($q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'], fn ($q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest('id')
            ->limit(2000)
            ->get();

        return Inertia::render('Admin/Audit/Index', [
            'filters' => $filters,
            'logs' => $logs->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user?->name ?? 'guest',
                'action' => $log->action,
                'file' => $log->file_name,
                'meta' => $log->meta,
                'ip' => $log->ip,
                'at' => $log->created_at?->format('Y-m-d H:i:s'),
            ]),
        ]);
    }
}
