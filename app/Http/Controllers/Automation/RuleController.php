<?php

namespace App\Http\Controllers\Automation;

use App\Automation\Actions\ActionRegistry;
use App\Automation\Events\AutomationEventRegistry;
use App\Http\Controllers\Controller;
use App\Models\AutomationRule;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RuleController extends Controller
{
    public function index(Request $request, ActionRegistry $actions, AutomationEventRegistry $events)
    {
        $userId = auth()->id();
        $rules = AutomationRule::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->orderByDesc('updated_at')
            ->get();

        return Inertia::render('Automation/Rules/Index', [
            'rules' => $rules->map(fn (AutomationRule $r) => $this->shape($r))->values(),
            'events' => array_map(fn ($d) => $d->type, $events->list()),
            'eventDescriptions' => collect($events->list())->mapWithKeys(fn ($d) => [$d->type => $d->description])->all(),
            'actionTypes' => $actions->types(),
            'scopes' => AutomationRule::SCOPES,
        ]);
    }

    public function store(Request $request, ActionRegistry $actions)
    {
        $data = $this->validateRule($request, $actions);
        AutomationRule::create($data + [
            'user_id' => auth()->id(),
            'scope' => AutomationRule::SCOPE_PERSONAL,
        ]);

        return back()->with('success', 'Rule created.');
    }

    public function update(Request $request, AutomationRule $rule, ActionRegistry $actions)
    {
        $this->authorize('update', $rule);
        $data = $this->validateRule($request, $actions, partial: true);
        $rule->update($data);

        return back()->with('success', 'Rule updated.');
    }

    public function destroy(AutomationRule $rule)
    {
        $this->authorize('delete', $rule);
        $rule->delete();

        return back()->with('success', 'Rule deleted.');
    }

    public function toggle(AutomationRule $rule)
    {
        $this->authorize('update', $rule);
        $rule->update(['enabled' => ! $rule->enabled]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRule(Request $request, ActionRegistry $actions, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $validated = $request->validate([
            'name' => $required.'|string|max:120',
            'description' => 'nullable|string|max:255',
            'enabled' => 'boolean',
            'trigger_event' => $required.'|string|max:64',
            'conditions_json' => $required.'|array',
            'actions_json' => $required.'|array|min:1',
        ]);

        foreach ($validated['actions_json'] as $i => $action) {
            $type = $action['type'] ?? null;
            if (! $type || ! $actions->has($type)) {
                throw ValidationException::withMessages([
                    "actions_json.$i.type" => "Unknown action type: {$type}",
                ]);
            }
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(AutomationRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'enabled' => (bool) $rule->enabled,
            'scope' => $rule->scope,
            'trigger_event' => $rule->trigger_event,
            'conditions_json' => $rule->conditions_json,
            'actions_json' => $rule->actions_json,
            'run_count' => $rule->run_count,
            'last_run_at' => optional($rule->last_run_at)->toIso8601String(),
            'updated_at' => optional($rule->updated_at)->toIso8601String(),
        ];
    }
}
