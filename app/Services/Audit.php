<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\File;
use Illuminate\Support\Facades\Request;

class Audit
{
    /**
     * Record an auditable action. $file may be a File or null (e.g. a guest
     * link download where we still snapshot the file name).
     *
     * @param  array<string, mixed>  $meta
     */
    public static function log(string $action, ?File $file = null, array $meta = [], ?int $userId = null): void
    {
        AuditLog::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'file_id' => $file?->getKey(),
            'file_name' => $file?->name,
            'meta' => $meta ?: null,
            'ip' => Request::ip(),
        ]);
    }
}
