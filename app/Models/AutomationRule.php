<?php

namespace App\Models;

use Database\Factories\AutomationRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The platform's generic automation rule. Scope:
 *   - personal  (user_id required, evaluates per-user)
 *   - team      (group_id set, evaluates for every user in the group)
 *   - system    (user_id null, evaluates globally — admin-authored)
 *
 * The engine never hard-codes any event type; trigger_event follows the
 * dotted namespace (rss.item.created, calendar.event.updated, …) and may
 * be a wildcard ("rss.item.*", "calendar.*") for fan-out rules.
 */
class AutomationRule extends Model
{
    /** @use HasFactory<AutomationRuleFactory> */
    use HasFactory;

    public const SCOPE_PERSONAL = 'personal';

    public const SCOPE_TEAM = 'team';

    public const SCOPE_SYSTEM = 'system';

    public const SCOPES = [
        self::SCOPE_PERSONAL,
        self::SCOPE_TEAM,
        self::SCOPE_SYSTEM,
    ];

    protected $fillable = [
        'user_id',
        'group_id',
        'scope',
        'name',
        'description',
        'enabled',
        'trigger_event',
        'event_match',
        'conditions_json',
        'actions_json',
        'run_count',
        'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'conditions_json' => 'array',
            'actions_json' => 'array',
            'event_match' => 'array',
            'run_count' => 'integer',
            'last_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(AutomationRuleExecution::class, 'rule_id');
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeForEvent(Builder $query, string $event): Builder
    {
        return $query->where('trigger_event', $event);
    }

    /**
     * Rules the given user owns OR that target their group OR system rules.
     * System rules are not bound to a user, so user_id is null.
     */
    public function scopeApplicableTo(Builder $query, int $userId, ?int $groupId = null): Builder
    {
        return $query->where(function (Builder $q) use ($userId, $groupId) {
            $q->where(fn (Builder $own) => $own->where('user_id', $userId))
                ->orWhere('scope', self::SCOPE_SYSTEM);
            if ($groupId) {
                $q->orWhere(fn (Builder $g) => $g->where('scope', self::SCOPE_TEAM)->where('group_id', $groupId));
            }
        });
    }
}
