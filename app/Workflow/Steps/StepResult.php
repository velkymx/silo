<?php

namespace App\Workflow\Steps;

/**
 * Outcome of running a single Step. v1 has only two statuses:
 *   - ok   : the step completed; follow Activity::next
 *   - fail : the step errored; the runtime records the rule as failed
 *           (or skipped, for a CONDITION that didn't match)
 *
 * Reserved for v2+ (NOT IMPLEMENTED): branch, delayed, unsupported.
 */
final class StepResult
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAIL = 'fail';

    public function __construct(
        public readonly string $status,
        public readonly ?string $error = null,
    ) {
        if (! in_array($status, [self::STATUS_OK, self::STATUS_FAIL], true)) {
            throw new \InvalidArgumentException("Unknown step status: {$status}");
        }
    }

    public static function ok(): self
    {
        return new self(self::STATUS_OK);
    }

    public static function fail(string $error): self
    {
        return new self(self::STATUS_FAIL, $error);
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK;
    }

    public function isFail(): bool
    {
        return $this->status === self::STATUS_FAIL;
    }
}
