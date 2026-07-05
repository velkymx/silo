<?php

namespace Tests\Unit;

use App\Services\DailyWordGame;
use Carbon\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class DailyWordGameServiceTest extends TestCase
{
    public function test_word_length_and_max_guesses(): void
    {
        $game = new DailyWordGame();

        $this->assertSame(5, $game->wordLength());
        $this->assertSame(6, $game->maxGuesses());
    }

    public function test_target_is_deterministic_per_date(): void
    {
        $game = new DailyWordGame();
        $date = Carbon::create(2026, 6, 24);

        $first = $game->targetForDate($date);
        $second = $game->targetForDate($date);

        $this->assertSame($first, $second);
        $this->assertSame(5, strlen($first));
    }

    public function test_no_word_repeats_within_a_full_cycle(): void
    {
        // The old crc32(date) picker repeated words within days of each
        // other; the seeded-shuffle walk must use every word exactly once
        // per pool-sized cycle.
        $game = new DailyWordGame();
        $poolSize = count(config('dwg.words'));

        $seen = [];
        $date = Carbon::create(2026, 1, 1);
        for ($i = 0; $i < $poolSize; $i++) {
            $word = $game->targetForDate($date->copy()->addDays($i));
            $this->assertArrayNotHasKey($word, $seen, "'{$word}' repeated within one cycle");
            $seen[$word] = true;
        }

        $this->assertCount($poolSize, $seen);
    }

    public function test_consecutive_days_get_different_words(): void
    {
        $game = new DailyWordGame();
        $date = Carbon::create(2026, 7, 5);

        $this->assertNotSame(
            $game->targetForDate($date),
            $game->targetForDate($date->copy()->addDay()),
        );
    }

    public function test_validates_words(): void
    {
        $game = new DailyWordGame();

        $this->assertTrue($game->isValidWord('apple'));
        $this->assertFalse($game->isValidWord('xxxxx'));
        $this->assertFalse($game->isValidWord('apples'));
        $this->assertFalse($game->isValidWord('app1e'));
    }

    public function test_evaluate_exact_match(): void
    {
        $game = new DailyWordGame();

        $this->assertSame(
            ['correct', 'correct', 'correct', 'correct', 'correct'],
            $game->evaluate('apple', 'apple')
        );
    }

    public function test_evaluate_mixed_status(): void
    {
        $game = new DailyWordGame();

        // Target: abbey, Guess: babel
        // Index 2 b and index 3 e are correct. The b at index 0 and the a at index 1 are present.
        // l at index 4 is absent.
        $this->assertSame(
            ['present', 'present', 'correct', 'correct', 'absent'],
            $game->evaluate('babel', 'abbey')
        );
    }

    public function test_evaluate_invalid_length_throws(): void
    {
        $game = new DailyWordGame();

        $this->expectException(InvalidArgumentException::class);
        $game->evaluate('apples', 'apple');
    }
}
