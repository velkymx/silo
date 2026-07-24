<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\File;
use Illuminate\Support\Facades\Request;

class Audit
{
    /**
     * Record an auditable action. The first two args cover the file-centric
     * shape (the historical dominant case). For non-File subjects, pass
     * null for $file and use $subjectName as the snapshot label that
     * survives deletion — the same column (`file_name`) doubles as a
     * human-readable name for any subject.
     *
     * @param  array<string, mixed>  $meta
     */
    public static function log(string $action, ?File $file = null, array $meta = [], ?int $userId = null, ?string $subjectName = null): void
    {
        AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'file_id' => $file?->getKey(),
            'file_name' => $subjectName ?? $file?->name,
            'meta' => $meta ?: null,
            'ip' => Request::ip(),
        ]);
    }
}
