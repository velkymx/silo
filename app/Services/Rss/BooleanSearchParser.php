<?php

namespace App\Services\Rss;

use Illuminate\Database\Eloquent\Builder;

/**
 * Tiny boolean-search parser for the inbox. The query string is split
 * on uppercase AND / OR / NOT tokens (the user types them explicitly,
 * so we never need to guess at intent), each remaining term is a
 * case-insensitive substring matched against title / excerpt /
 * author / feed title, and the whole expression is reduced to a
 * nested where clause.
 *
 *   "laravel"                → title OR excerpt OR author LIKE '%laravel%'
 *   "laravel AND php"        → (laravel) AND (php)
 *   "laravel OR wordpress"   → (laravel) OR (wordpress)
 *   "laravel NOT wordpress"   → (laravel) AND NOT (wordpress)
 *   "(laravel OR wordpress) AND tips" → grouped OR inside, ANDed
 *
 * Falls back to the same flat-OR-on-terms behaviour as before when
 * the string contains no operators, so the inbox's existing search
 * is fully backward compatible.
 */
class BooleanSearchParser
{
    /**
     * @param  Builder<\App\Models\RssItem>  $query
     * @return Builder<\App\Models\RssItem>
     */
    public function apply(Builder $query, string $raw): Builder
    {
        $raw = trim($raw);
        if ($raw === '') {
            return $query;
        }

        $tokens = $this->tokenize($raw);
        if (! $this->hasOperator($tokens)) {
            return $this->applyFlatOr($query, $this->splitWords($raw));
        }

        $ast = $this->parseTokens($tokens);
        $this->applyAst($query, $ast);

        return $query;
    }

    /**
     * @param  Builder<\App\Models\RssItem>  $query
     * @param  array<int, string>  $terms
     * @return Builder<\App\Models\RssItem>
     */
    private function applyFlatOr(Builder $query, array $terms): Builder
    {
        if ($terms === []) {
            return $query;
        }

        return $query->where(function (Builder $w) use ($terms) {
            $first = true;
            foreach ($terms as $term) {
                $callback = function (Builder $sub) use ($term) {
                    $this->matchTerm($sub, $term);
                };
                if ($first) {
                    $w->where($callback);
                    $first = false;
                } else {
                    $w->orWhere($callback);
                }
            }
        });
    }

    /**
     * @param  Builder<\App\Models\RssItem>  $query
     */
    private function applyAst(Builder $query, array $ast): void
    {
        $op = $ast['op'] ?? 'AND';
        $children = $ast['children'] ?? [];

        if ($op === 'NOT') {
            $query->where(function (Builder $w) use ($children) {
                $inner = $children[0] ?? ['term' => ''];
                $w->whereNot(function (Builder $q) use ($inner) {
                    if (isset($inner['op'])) {
                        $this->applyAst($q, $inner);
                    } else {
                        $this->matchTerm($q, $inner['term']);
                    }
                });
            });

            return;
        }

        $query->where(function (Builder $w) use ($op, $children) {
            $method = $op === 'OR' ? 'orWhere' : 'where';
            foreach ($children as $i => $child) {
                $childFn = function (Builder $q) use ($child) {
                    if (isset($child['op'])) {
                        $this->applyAst($q, $child);
                    } else {
                        $this->matchTerm($q, $child['term']);
                    }
                };
                $w->{$method}($childFn);
            }
        });
    }

    /**
     * @param  Builder<\App\Models\RssItem>  $query
     */
    private function matchTerm(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            $query->whereRaw('0 = 1');

            return;
        }
        $like = '%'.$term.'%';
        $query->where(function (Builder $w) use ($like) {
            $w->where('title', 'like', $like)
                ->orWhere('excerpt', 'like', $like)
                ->orWhere('author', 'like', $like)
                ->orWhereHas('feed', function (Builder $fw) use ($like) {
                    $fw->where('title', 'like', $like);
                });
        });
    }

    /**
     * Tokenise the input on whitespace, with parentheses as their own
     * tokens so the recursive descent can find sub-expressions.
     *
     * @return array<int, string>
     */
    public function tokenize(string $raw): array
    {
        $tokens = [];
        $buffer = '';
        $len = mb_strlen($raw);
        for ($i = 0; $i < $len; $i++) {
            $c = mb_substr($raw, $i, 1);
            if ($c === '(' || $c === ')') {
                if (trim($buffer) !== '') {
                    foreach ($this->splitOnWhitespace($buffer) as $tok) {
                        $tokens[] = $tok;
                    }
                    $buffer = '';
                }
                $tokens[] = $c;

                continue;
            }
            $buffer .= $c;
        }
        if (trim($buffer) !== '') {
            foreach ($this->splitOnWhitespace($buffer) as $tok) {
                $tokens[] = $tok;
            }
        }

        return $tokens;
    }

    /**
     * @return array<int, string>
     */
    private function splitOnWhitespace(string $s): array
    {
        return array_values(array_filter(preg_split('/\s+/', trim($s)) ?: [], fn ($p) => $p !== ''));
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function hasOperator(array $tokens): bool
    {
        foreach ($tokens as $t) {
            if (in_array($t, ['AND', 'OR', 'NOT'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $raw
     * @return array<string, mixed>
     */
    public function parseTokens(array $tokens): array
    {
        $pos = 0;

        return $this->parseOr($tokens, $pos);
    }

    /**
     * Recursive descent: OR has lowest precedence, then AND, then NOT.
     * Each call returns the parsed AST and advances $pos by reference.
     *
     * @param  array<int, string>  $tokens
     * @param  int  $pos
     * @return array<string, mixed>
     */
    private function parseOr(array $tokens, int &$pos): array
    {
        $left = $this->parseAnd($tokens, $pos);
        $children = [$left];
        while ($pos < count($tokens) && $tokens[$pos] === 'OR') {
            $pos++;
            $children[] = $this->parseAnd($tokens, $pos);
        }

        return count($children) === 1 ? $left : ['op' => 'OR', 'children' => $children];
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  int  $pos
     * @return array<string, mixed>
     */
    private function parseAnd(array $tokens, int &$pos): array
    {
        $left = $this->parseNot($tokens, $pos);
        $children = [$left];
        while ($pos < count($tokens) && ($tokens[$pos] === 'AND' || $tokens[$pos] === 'NOT')) {
            if ($tokens[$pos] === 'AND') {
                $pos++;
                $children[] = $this->parseNot($tokens, $pos);
            } else {
                // Implicit AND before NOT — "a NOT b" reads as "a AND NOT b".
                $pos++;
                $inner = $this->parseAtom($tokens, $pos);
                $children[] = ['op' => 'NOT', 'children' => [$inner]];
            }
        }

        return count($children) === 1 ? $left : ['op' => 'AND', 'children' => $children];
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  int  $pos
     * @return array<string, mixed>
     */
    private function parseNot(array $tokens, int &$pos): array
    {
        if ($pos < count($tokens) && $tokens[$pos] === 'NOT') {
            $pos++;
            $inner = $this->parseAtom($tokens, $pos);

            return ['op' => 'NOT', 'children' => [$inner]];
        }

        return $this->parseAtom($tokens, $pos);
    }

    /**
     * @param  array<int, string>  $tokens
     * @param  int  $pos
     * @return array<string, mixed>
     */
    private function parseAtom(array $tokens, int &$pos): array
    {
        if ($pos >= count($tokens)) {
            return ['term' => ''];
        }
        $t = $tokens[$pos];
        if ($t === '(') {
            $pos++;
            $inner = $this->parseOr($tokens, $pos);
            if ($pos < count($tokens) && $tokens[$pos] === ')') {
                $pos++;
            }

            return $inner;
        }
        if ($t === ')') {
            $pos++;

            return ['term' => ''];
        }
        $pos++;

        return ['term' => $t];
    }

    /**
     * @return array<int, string>
     */
    private function splitWords(string $raw): array
    {
        $parts = preg_split('/\s+/', trim($raw));

        return array_values(array_filter($parts ?? [], fn ($p) => $p !== ''));
    }
}
