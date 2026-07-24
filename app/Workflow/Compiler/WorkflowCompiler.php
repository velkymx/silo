<?php

namespace App\Workflow\Compiler;

use App\Models\AutomationRule;
use App\Workflow\Activity;
use App\Workflow\ActivityType;

/**
 * Linear v1 compiler. Input: an AutomationRule. Output: an ordered
 * array of activities (a v1 workflow has no branches, no cycles, no
 * metadata — an array is enough).
 *
 *   CONDITION → ACTION × N → END
 *
 * The runtime receives the array plus the first activity id and walks
 * the chain. When workflows grow metadata (branches, variables,
 * versions), introduce a `CompiledWorkflow` value object here.
 */
class WorkflowCompiler
{
    /**
     * @return array{start: string, activities: array<string, Activity>}
     */
    public function compile(AutomationRule $rule, string $slug): array
    {
        $activities = [];

        // 1) CONDITION — the rule's conditions_json.
        $conditionId = "{$slug}.condition.1";
        $activities[$conditionId] = new Activity(
            id: $conditionId,
            type: ActivityType::CONDITION,
            name: 'Match conditions',
            data: ['condition' => $rule->conditions_json ?? []],
            next: null, // filled in below
        );

        // 2) ACTIONs — the rule's actions_json, in order.
        $previousId = $conditionId;
        $actionList = is_array($rule->actions_json) ? $rule->actions_json : [];
        foreach ($actionList as $i => $raw) {
            $activityId = "{$slug}.action.".($i + 1);
            $activities[$activityId] = Activity::fromArray(is_array($raw) ? $raw : [], $activityId);
            $activities[$previousId] = $this->link($activities[$previousId], $activityId);
            $previousId = $activityId;
        }

        // 3) END.
        $endId = "{$slug}.end.1";
        $activities[$endId] = new Activity(
            id: $endId,
            type: ActivityType::END,
            name: 'Finish',
            next: null,
        );
        $activities[$previousId] = $this->link($activities[$previousId], $endId);

        return [
            'start' => $conditionId,
            'activities' => $activities,
        ];
    }

    private function link(Activity $a, string $nextId): Activity
    {
        return new Activity(
            id: $a->id,
            type: $a->type,
            name: $a->name,
            action: $a->action,
            data: $a->data,
            next: $nextId,
        );
    }
}
