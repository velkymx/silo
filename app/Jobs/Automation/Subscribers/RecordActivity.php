<?php

namespace App\Jobs\Automation\Subscribers;

use App\Models\AuditLog;
use App\Models\File;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Appends a row to the existing audit log. Every event that lands here
 * becomes part of the activity stream that powers dashboards and the
 * future MCP surface.
 */
class RecordActivity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $userId,
        public string $action,
        public int $entityId,
        public ?string $entityType = null,
        /** @var array<string, mixed> */
        public array $meta = [],
        public ?Carbon $occurredAt = null,
    ) {}

    public function handle(): void
    {
        $isFile = $this->entityType === File::class;

        AuditLog::create([
            'user_id' => $this->userId,
            'action' => $this->action,
            'file_id' => $isFile ? $this->entityId : null,
            'file_name' => null,
            'meta' => array_merge($this->meta, [
                'entity_type' => $this->entityType,
                'entity_id' => $this->entityId,
                'occurred_at' => $this->occurredAt?->toIso8601String(),
            ]),
            'ip' => null,
        ]);
    }
}
