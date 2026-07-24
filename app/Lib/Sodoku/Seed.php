<?php

namespace App\Lib\Sodoku;

/**
 * PHP port of resources/js/lib/sodoku/seed.ts.
 *
 * Deterministic seeding for the daily-puzzle feature. Same date + same
 * difficulty → same seed → same puzzle. Different days → different
 * puzzles. The FNV-1a hash matches the JS implementation bit-for-bit
 * (modulo the 32-bit integer width, which is the same on both sides).
 */
class Seed
{
    public const DIFFICULTY_BEGINNER = 'beginner';
    public const DIFFICULTY_INTERMEDIATE = 'intermediate';
    public const DIFFICULTY_ADVANCED = 'advanced';

    private const DIFFICULTY_SALT = [
        self::DIFFICULTY_BEGINNER => 0x4f1e2d3c,
        self::DIFFICULTY_INTERMEDIATE => 0x6a7b8c9d,
        self::DIFFICULTY_ADVANCED => 0x1e2f3a4b,
    ];

    /**
     * FNV-1a 32-bit hash. Matches the JS implementation.
     */
    public static function fnv1a(string $s): int
    {
        $h = 0x811c9dc5;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $h ^= ord($s[$i]);
            // PHP's integer is 64-bit signed on 64-bit builds, so we mask to
            // 32 bits before the multiply to keep parity with JS.
            $h = ($h * 0x01000193) & 0xffffffff;
        }
        return $h;
    }

    /**
     * Returns a positive 32-bit integer seed for the (date, difficulty) pair.
     */
    public static function seedForDate(string $isoDate, string $difficulty): int
    {
        $salt = self::DIFFICULTY_SALT[$difficulty] ?? 0x0;
        $h = self::fnv1a($isoDate . ':' . $salt);
        return $h === 0 ? 1 : $h;
    }

    /**
     * Returns a stable, URL-safe 8-char id derived from a numeric seed.
     * Pads to 8 chars with leading zeros (matches the JS implementation).
     */
    public static function deterministicId(int $seed): string
    {
        return str_pad((string) base_convert((string) $seed, 10, 36), 8, '0', STR_PAD_LEFT);
    }
}
