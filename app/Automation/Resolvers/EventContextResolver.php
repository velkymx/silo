<?php

namespace App\Automation\Resolvers;

use App\Automation\Events\AutomationEvent;

/**
 * A pluggable mapper: given an event, return the flat context dictionary
 * that conditions + actions operate on. Each producer registers one so the
 * engine never learns about RSS / Calendar / File specifics.
 */
interface EventContextResolver
{
    /**
     * @return array<string, mixed> flat associative array; values may be
     *                              scalar, list, or hydrated model — the
     *                              evaluator only stringifies scalars.
     */
    public function resolve(AutomationEvent $event): array;
}
