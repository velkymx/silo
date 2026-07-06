<?php

namespace App\Models;

use Database\Factories\AutomationRuleExecutionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (rule, source event) the engine attempted. The unique key on
 * (rule_id, event_key) is the idempotency guard — replayed jobs short-circuit
 * instead of re-running side effects.
 */
class AutomationRuleExecution extends Model
{
    /** @use HasFactory<AutomationRuleExecutionFactory> */
    use HasFactory;

    public const STATUS_MATCHED = 'matched';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'rule_id',
        'user_id',
        'trigger_event',
        'occurred_at',
        'event_key',
        'event_type',
        'conditions_evaluated',
        'actions_executed',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'conditions_evaluated' => 'array',
            'actions_executed' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AutomationRule::class, 'rule_id');
    }
}
