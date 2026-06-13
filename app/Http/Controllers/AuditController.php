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
        // The VibeDataTable paginates client-side; hand it a capped recent window.
        $logs = AuditLog::with('user:id,name')->latest('id')->limit(2000)->get();

        return Inertia::render('Admin/Audit/Index', [
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
