<?php

namespace App\Automation;

use App\Automation\Events\AutomationEvent;
use App\Automation\Events\AutomationEventRegistry;
use App\Automation\Events\EventOrigin;
use App\Automation\Resolvers\EventContextResolver;
use App\Automation\Subscribers\SubscriberRegistry;
use App\Jobs\Automation\ExecuteAutomationRules;
use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use App\Models\User;
use App\Workflow\Compiler\WorkflowCompiler;
use App\Workflow\Runtime\WorkflowRuntime;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * The platform's single entry point for "something happened" signals.
 * Producers never see a rule, a notification, or an action handler.
 *
 * Flow (per dispatch):
 *   1. Hydrate the AutomationEvent.
 *   2. Run platform subscribers synchronously, ordered by priority.
 *   3. Enqueue the workflow runtime to fan out to per-rule actions.
 *   4. Return — the producer's request lifecycle is never blocked.
 */
class AutomationDispatcher implements EventDispatcher
{
    public function __construct(
        private readonly SubscriberRegistry $subscribers,
        private readonly BusDispatcher $bus,
    ) {}

    public function dispatch(
        string $type,
        int $userId,
        array $payload = [],
        ?string $key = null,
        ?Carbon $occurredAt = null,
        EventOrigin $origin = EventOrigin::WEB,
        array $metadata = [],
    ): void {
        $event = AutomationEvent::make($type, $userId, $payload, $key, $occurredAt, $origin, $metadata);

        foreach ($this->subscribers->for($event) as $subscriber) {
            try {
                $subscriber->handle($event);
            } catch (\Throwable $e) {
                Log::warning('automation.subscriber.failed', [
                    'event' => $type,
                    'subscriber' => $subscriber::class,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->bus->dispatch(new ExecuteAutomationRules($event));

        Log::info('automation.event.received', $event->toArray());
    }

    /**
     * Run one rule against one event. Called by ExecuteAutomationRules.
     * Also exposed for the manual replay endpoint and tests.
     *
     * The rule is compiled into a workflow and handed to the
     * WorkflowRuntime; this method is the bridge between the rule
     * representation (JSON in the DB) and the workflow representation
     * (compiled executable plan).
     */
    public function evaluateRule(AutomationRule $rule, AutomationEvent $event): AutomationRuleExecution
    {
        $resolver = app(EventContextResolver::class);
        $context = $resolver->resolve($event);
        $context['event_type'] = $event->type;
        $context['event_origin'] = $event->origin->value;
        $context['occurred_at'] = $event->occurredAt?->toIso8601String();

        try {
            $compiled = app(WorkflowCompiler::class)
                ->compile($rule, "rule.{$rule->id}");
        } catch (\Throwable $e) {
            Log::warning('automation.compile.failed', [
                'rule' => $rule->id,
                'error' => $e->getMessage(),
            ]);

            return $this->record($rule, $event, AutomationRuleExecution::STATUS_FAILED, [], [], $e->getMessage());
        }

        $result = app(WorkflowRuntime::class)
            ->run($compiled['start'], $compiled['activities'], $rule, $event, $context);

        if ($result['status'] !== AutomationRuleExecution::STATUS_FAILED) {
            AutomationRule::whereKey($rule->id)->update([
                'run_count' => $rule->run_count + 1,
                'last_run_at' => now(),
            ]);
        }

        return $this->record(
            $rule,
            $event,
            $result['status'],
            is_array($result['conditions']) ? $result['conditions'] : [],
            is_array($result['actions']) ? $result['actions'] : [],
            $result['error'],
        );
    }

    /** Find the rules a user is allowed to evaluate. */
    public function rulesFor(int $userId, string $eventType): Collection
    {
        $user = User::find($userId);
        $groupId = $user?->group_id;

        return AutomationRule::query()
            ->enabled()
            ->applicableTo($userId, $groupId)
            ->get()
            ->filter(function (AutomationRule $rule) use ($eventType) {
                if ($rule->trigger_event === $eventType) {
                    return true;
                }
                $registry = app(AutomationEventRegistry::class);

                return $registry->matches($rule->trigger_event, $eventType);
            });
    }

    private function record(
        AutomationRule $rule,
        AutomationEvent $event,
        string $status,
        array $conditions,
        array $actions,
        ?string $error,
    ): AutomationRuleExecution {
        $key = $event->idempotencyKey();

        $existing = AutomationRuleExecution::where('rule_id', $rule->id)
            ->where('event_key', $key)
            ->first();
        if ($existing) {
            return $existing;
        }

        return AutomationRuleExecution::create([
            'rule_id' => $rule->id,
            'user_id' => $event->userId,
            'trigger_event' => $rule->trigger_event,
            'occurred_at' => $event->occurredAt,
            'event_key' => $key,
            'event_type' => $event->type,
            'conditions_evaluated' => $conditions,
            'actions_executed' => $actions,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
