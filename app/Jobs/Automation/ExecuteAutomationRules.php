<?php

namespace App\Jobs\Automation;

use App\Automation\AutomationDispatcher;
use App\Automation\Events\AutomationEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async worker for user automations. One job per event (not per rule)
 * so the dispatcher can leverage queue batching and a single attempt
 * covers all of a user's rules. System reactions already ran
 * synchronously in the dispatcher; this job is purely the user-rule
 * layer.
 */
class ExecuteAutomationRules implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 60;

    public function __construct(public AutomationEvent $event) {}

    public function handle(AutomationDispatcher $engine): void
    {
        $rules = $engine->rulesFor($this->event->userId, $this->event->type);
        foreach ($rules as $rule) {
            try {
                $engine->evaluateRule($rule, $this->event);
            } catch (\Throwable $e) {
                Log::warning('automation.rule.failed', [
                    'rule' => $rule->id,
                    'event' => $this->event->type,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
