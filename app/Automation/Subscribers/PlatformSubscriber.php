<?php

namespace App\Automation\Subscribers;

use App\Automation\Events\AutomationEvent;

/**
 * Infrastructure subscriber: runs on every event regardless of user
 * rules. Search re-indexing, audit logging, metrics, cache invalidation
 * — the platform responsibilities that must work even for a user with
 * no rules.
 *
 * Distinct from user automations, which fire only when a rule's
 * conditions match. The two layers share the dispatcher but never each
 * other.
 *
 * Subscribers run in priority order (lowest first). Default is 100;
 * metrics/instrumentation should run early so a subscriber failure
 * doesn't poison the audit log; user-visible side effects should
 * run later.
 */
interface PlatformSubscriber
{
    /** The dotted event type this subscriber cares about, or '*' for every event. */
    public function subscribesTo(): string;

    /** Lower runs first. Tie → registration order. */
    public function priority(): int;

    /**
     * Run the side effect. MUST be idempotent (the dispatcher may
     * re-run an event with the same key). Keep this cheap — expensive
     * work belongs in a queued reaction.
     */
    public function handle(AutomationEvent $event): void;
}
