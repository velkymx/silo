<?php

namespace App\Models;

use Database\Factories\WorkflowTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable, opinionated AutomationRule template. Users can clone a
 * template into a personal rule, which copies its conditions and
 * actions and lets the user customize from there. Today templates are
 * static seed rows; future iterations may add a CRUD UI or a community
 * library.
 */
class WorkflowTemplate extends Model
{
    /** @use HasFactory<WorkflowTemplateFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'trigger_event',
        'conditions_json',
        'actions_json',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'conditions_json' => 'array',
            'actions_json' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Materialize this template as a new AutomationRule for the given
     * user. The rule is unsaved; the caller decides scope + persistence.
     */
    public function toRule(int $userId, string $name, ?string $description = null): AutomationRule
    {
        return new AutomationRule([
            'user_id' => $userId,
            'scope' => AutomationRule::SCOPE_PERSONAL,
            'name' => $name,
            'description' => $description ?? $this->description,
            'enabled' => true,
            'trigger_event' => $this->trigger_event,
            'conditions_json' => $this->conditions_json,
            'actions_json' => $this->actions_json,
        ]);
    }
}
