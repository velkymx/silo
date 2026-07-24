<?php

namespace App\Automation\Actions;

use App\Models\AutomationRule;

interface ActionHandler
{
    /** Stable identifier used in rule JSON. */
    public function type(): string;

    /**
     * Execute the action. Implementations MUST be idempotent — the dispatcher
     * may retry the whole event, and the same action may re-run with the
     * same args. Throw on hard failure (logged + marked failed), return
     * normally otherwise.
     *
     * @param  array<string, mixed>  $data  the action's "data" payload
     * @param  array<string, mixed>  $context  the resolved event context
     */
    public function execute(AutomationRule $rule, array $data, array $context): void;
}
