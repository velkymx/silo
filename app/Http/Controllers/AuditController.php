<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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

    public function index()
    {
        $logs = AuditLog::with('user:id,name')->latest('id')->paginate(50);

        return Inertia::render('Admin/Audit/Index', [
            'logs' => collect($logs->items())->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user?->name ?? 'guest',
                'action' => $log->action,
                'file' => $log->file_name,
                'meta' => $log->meta,
                'ip' => $log->ip,
                'at' => $log->created_at?->format('Y-m-d H:i:s'),
            ]),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
