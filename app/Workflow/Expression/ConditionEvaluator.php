<?php

namespace App\Workflow\Expression;

use Illuminate\Support\Facades\Log;

/**
 * Evaluates a structured condition object against an ExecutionContext's
 * resolved context. AND-reduces every key.
 *
 * Why structured JSON and not a free-form expression language? Because
 * going from JSON to a richer expression is a one-way ticket; the
 * reverse is a parser. We start with the constrained form so the data
 * model stays stable as we add operators.
 *
 * Today the operators are a small closed set. New operators are added
 * here and matched against `OPERATORS`. The runtime doesn't branch on
 * specific keys; the evaluator does.
 */
class ConditionEvaluator
{
    /** Operator → context key it reads. */
    private const CONTAINS_KEYS = [
        'title_contains' => 'item_title',
        'excerpt_contains' => 'item_excerpt',
        'author_contains' => 'item_author',
        'url_contains' => 'item_url',
        'guid_contains' => 'item_guid',
        'feed_title_contains' => 'feed_title',
        'feed_url_contains' => 'feed_url',
        'feed_folder_contains' => 'feed_folder',
    ];

    private const EQUALS_KEYS = [
        'feed_id' => 'feed_id',
        'user_id' => 'user_id',
    ];

    /**
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $context  the resolved context (NOT the ExecutionContext)
     * @return array{ok: bool, details: array<string, array<string, mixed>>}
     */
    public function evaluate(array $conditions, array $context): array
    {
        $details = [];
        foreach ($conditions as $key => $value) {
            $details[(string) $key] = $this->evaluateOne((string) $key, $value, $context);
        }
        $ok = ! in_array(false, array_column($details, 'matched'), true);

        return ['ok' => $ok, 'details' => $details];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{matched: bool, value: mixed, against?: mixed, note?: string}
     */
    private function evaluateOne(string $key, mixed $value, array $context): array
    {
        if (array_key_exists($key, self::CONTAINS_KEYS)) {
            $ctxKey = self::CONTAINS_KEYS[$key];
            $haystack = $context[$ctxKey] ?? null;
            $matched = is_string($haystack) && is_string($value) && $value !== ''
                && str_contains(strtolower($haystack), strtolower($value));

            return ['matched' => $matched, 'value' => $value, 'against' => $haystack];
        }
        if (array_key_exists($key, self::EQUALS_KEYS)) {
            $ctxKey = self::EQUALS_KEYS[$key];
            $actual = $context[$ctxKey] ?? null;
            $matched = $actual != null && (is_numeric($value) ? (int) $value === (int) $actual : $value == $actual);

            return ['matched' => (bool) $matched, 'value' => $value, 'against' => $actual];
        }
        if (str_ends_with($key, '_contains')) {
            $haystacks = array_filter($context, 'is_string');
            $matched = is_string($value) && $value !== '' && (bool) array_filter(
                $haystacks,
                fn (string $h) => str_contains(strtolower($h), strtolower($value)),
            );

            return ['matched' => $matched, 'value' => $value, 'against' => array_values($haystacks)];
        }
        Log::warning('workflow.expression.unknown_key', ['key' => $key]);

        return ['matched' => true, 'value' => $value, 'against' => null, 'note' => 'unknown-key-noop'];
    }
}
