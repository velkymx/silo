<?php

namespace App\Http\Controllers\Automation;

use App\Automation\AutomationDispatcher;
use App\Automation\Events\AutomationEvent;
use App\Automation\Events\EventOrigin;
use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RuleExecutionController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $ruleId = $request->integer('rule');
        $status = $request->string('status')->lower()->toString();

        $rows = AutomationRuleExecution::with('rule:id,name')
            ->where('user_id', $userId)
            ->when($ruleId > 0, fn ($q) => $q->where('rule_id', $ruleId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(fn (AutomationRuleExecution $e) => [
                'id' => $e->id,
                'rule_id' => $e->rule_id,
                'rule_name' => $e->rule?->name,
                'trigger_event' => $e->trigger_event,
                'event_key' => $e->event_key,
                'status' => $e->status,
                'error' => $e->error,
                'conditions_evaluated' => $e->conditions_evaluated,
                'actions_executed' => $e->actions_executed,
                'created_at' => optional($e->created_at)->toIso8601String(),
            ])
            ->values();

        $rules = AutomationRule::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'enabled']);

        return Inertia::render('Automation/Rules/Logs', [
            'executions' => $rows,
            'rules' => $rules->map(fn (AutomationRule $r) => ['id' => $r->id, 'name' => $r->name, 'enabled' => $r->enabled])->values(),
            'filters' => [
                'rule' => $ruleId ?: null,
                'status' => $status ?: null,
            ],
        ]);
    }

    /**
     * Replay an event by id. Constructs a fresh AutomationEvent from the
     * stored row and re-runs every rule that originally matched. The key
     * is overridden with a replay-specific value so downstream actions
     * re-fire instead of being deduped.
     */
    public function replay(int $ruleExecution, AutomationDispatcher $engine, Request $request)
    {
        $execution = AutomationRuleExecution::find($ruleExecution);
        abort_unless($execution, 404);

        $userId = auth()->id();
        abort_unless($execution->user_id === $userId, 403);

        $event = AutomationEvent::make(
            $execution->trigger_event,
            $userId,
            $request->input('payload', []),
            'replay:'.$execution->id.':'.now()->timestamp,
            $execution->occurred_at ?? now(),
            EventOrigin::REPLAY,
        );

        $rules = $engine->rulesFor($userId, $event->type);
        $matched = [];
        foreach ($rules as $rule) {
            $exec = $engine->evaluateRule($rule, $event);
            $matched[] = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'status' => $exec->status,
            ];
        }

        return back()->with('success', 'Replayed event. '.count($matched).' rule(s) ran.');
    }
}
