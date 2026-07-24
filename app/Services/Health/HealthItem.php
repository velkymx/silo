<?php

namespace App\Services\Health;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One operational health check. `status` drives both whether the item counts as
 * "needs attention" (warn/red do, ok/info do not) and how it is styled.
 * `detail` is educational — it explains why the item matters so the operator
 * can decide whether a disabled feature is intentional, rather than alarming.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class HealthItem implements Arrayable
{
    public const OK = 'ok';

    public const WARN = 'warn';

    public const RED = 'red';

    public const INFO = 'info';

    public function __construct(
        public string $category,
        public string $label,
        public string $status,
        public string $detail,
    ) {}

    /** Warn and red are the tiers that count as "needs attention". */
    public function needsAttention(): bool
    {
        return $this->status === self::WARN || $this->status === self::RED;
    }

    /**
     * @return array{category: string, label: string, status: string, detail: string}
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'label' => $this->label,
            'status' => $this->status,
            'detail' => $this->detail,
        ];
    }
}
