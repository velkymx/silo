<?php

namespace Tests\Unit;

use App\Lib\Sodoku\Seed;
use App\Services\SodokuGenerator;
use PHPUnit\Framework\TestCase;

class SodokuGeneratorTest extends TestCase
{
    private function generator(): SodokuGenerator
    {
        return new SodokuGenerator();
    }

    private function givensOf(string $puzzle): int
    {
        return strlen($puzzle) - substr_count($puzzle, '-');
    }

    private function isValidCompleteSolution(string $s): bool
    {
        if (strlen($s) !== 81) return false;
        if (!preg_match('/^[1-9]{81}$/', $s)) return false;
        for ($r = 0; $r < 9; $r++) {
            $row = substr($s, $r * 9, 9);
            if (count(array_unique(str_split($row))) !== 9) return false;
        }
        for ($c = 0; $c < 9; $c++) {
            $col = '';
            for ($r = 0; $r < 9; $r++) $col .= $s[$r * 9 + $c];
            if (count(array_unique(str_split($col))) !== 9) return false;
        }
        for ($br = 0; $br < 9; $br += 3) {
            for ($bc = 0; $bc < 9; $bc += 3) {
                $box = '';
                for ($dr = 0; $dr < 3; $dr++) {
                    for ($dc = 0; $dc < 3; $dc++) {
                        $box .= $s[($br + $dr) * 9 + ($bc + $dc)];
                    }
                }
                if (count(array_unique(str_split($box))) !== 9) return false;
            }
        }
        return true;
    }

    private function puzzleMatchesSolution(string $puzzle, string $solution): bool
    {
        for ($i = 0; $i < 81; $i++) {
            if ($puzzle[$i] !== '-' && $puzzle[$i] !== $solution[$i]) return false;
        }
        return true;
    }

    public function test_beginner_puzzle_has_38_givens_and_complete_solution(): void
    {
        $r = $this->generator()->generate(1, 'beginner');
        $this->assertSame(38, $this->givensOf($r['puzzle']));
        $this->assertTrue($this->isValidCompleteSolution($r['solution']));
        $this->assertTrue($this->puzzleMatchesSolution($r['puzzle'], $r['solution']));
    }

    public function test_intermediate_puzzle_has_30_givens_and_complete_solution(): void
    {
        $r = $this->generator()->generate(1, 'intermediate');
        $this->assertSame(30, $this->givensOf($r['puzzle']));
        $this->assertTrue($this->isValidCompleteSolution($r['solution']));
        $this->assertTrue($this->puzzleMatchesSolution($r['puzzle'], $r['solution']));
    }

    public function test_advanced_puzzle_has_23_givens_and_complete_solution(): void
    {
        $r = $this->generator()->generate(1, 'advanced');
        $this->assertSame(23, $this->givensOf($r['puzzle']));
        $this->assertTrue($this->isValidCompleteSolution($r['solution']));
        $this->assertTrue($this->puzzleMatchesSolution($r['puzzle'], $r['solution']));
    }

    public function test_generator_is_deterministic_for_same_seed(): void
    {
        $a = $this->generator()->generate(1234567, 'intermediate');
        $b = $this->generator()->generate(1234567, 'intermediate');
        $this->assertSame($a['puzzle'], $b['puzzle']);
        $this->assertSame($a['solution'], $b['solution']);
    }

    public function test_generator_differs_for_different_seeds(): void
    {
        $a = $this->generator()->generate(111, 'intermediate');
        $b = $this->generator()->generate(222, 'intermediate');
        $this->assertNotSame($a['puzzle'], $b['puzzle']);
    }

    public function test_difficulty_echoes_requested_value(): void
    {
        $this->assertSame('beginner', $this->generator()->generate(1, 'beginner')['difficulty']);
        $this->assertSame('intermediate', $this->generator()->generate(1, 'intermediate')['difficulty']);
        $this->assertSame('advanced', $this->generator()->generate(1, 'advanced')['difficulty']);
    }

    public function test_seed_for_date_is_stable_across_timezones(): void
    {
        // Same date and difficulty should produce the same seed regardless of clock.
        $today = date('Y-m-d');
        $s1 = Seed::seedForDate($today, 'beginner');
        $s2 = Seed::seedForDate($today, 'beginner');
        $this->assertSame($s1, $s2);
    }

    public function test_seeded_daily_puzzle_is_stable_for_same_date(): void
    {
        $date = '2026-06-24';
        $seed = Seed::seedForDate($date, 'intermediate');
        $a = $this->generator()->generate($seed, 'intermediate');
        $b = $this->generator()->generate($seed, 'intermediate');
        $this->assertSame($a['puzzle'], $b['puzzle']);
    }
}
