<?php

namespace App\Http\Controllers;

use App\Lib\Sodoku\Seed;
use App\Services\SodokuGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The Silo Sodoku game. A traditional 9×9 sudoku with three difficulty
 * tiers and a deterministic daily board (same date → same puzzle for
 * every user).
 *
 * The page server-renders with a puzzle baked into the Inertia props. The
 * `puzzle` is what the player sees (zeros = empty cells); the `solution`
 * is the known-good answer, used by the win-check on the client (and
 * also exposed to the client so the pencil-mark / hint features can
 * reason about what's "correct").
 */
class SodokuController extends Controller
{
    public function __construct(private readonly SodokuGenerator $generator) {}

    public function index(Request $request)
    {
        $difficulty = $this->resolveDifficulty($request);
        $seed = $this->resolveSeed($request, $difficulty);
        $puzzle = $this->generator->generate($seed, $difficulty);

        if ($request->boolean('_json')) {
            return response()->json([
                'puzzle' => $puzzle['puzzle'],
                'solution' => $puzzle['solution'],
                'difficulty' => $puzzle['difficulty'],
                'seed' => $seed,
            ]);
        }

        return Inertia::render('Break/Sodoku', [
            'puzzle' => $puzzle['puzzle'],
            'solution' => $puzzle['solution'],
            'difficulty' => $puzzle['difficulty'],
            'date' => now()->toDateString(),
            'seed' => $seed,
            'difficultyOptions' => [
                ['value' => 'beginner', 'text' => 'Beginner', 'sub' => '38 givens'],
                ['value' => 'intermediate', 'text' => 'Intermediate', 'sub' => '30 givens'],
                ['value' => 'advanced', 'text' => 'Advanced', 'sub' => '23 givens'],
            ],
        ]);
    }

    private function resolveSeed(Request $request, string $difficulty): int
    {
        $raw = $request->query('seed');
        if ($raw !== null && $raw !== '' && is_numeric($raw)) {
            return (int) $raw;
        }
        return Seed::seedForDate(now()->toDateString(), $difficulty);
    }

    private function resolveDifficulty(Request $request): string
    {
        $difficulty = (string) $request->query('difficulty', Seed::DIFFICULTY_BEGINNER);
        return in_array($difficulty, [
            Seed::DIFFICULTY_BEGINNER,
            Seed::DIFFICULTY_INTERMEDIATE,
            Seed::DIFFICULTY_ADVANCED,
        ], true) ? $difficulty : Seed::DIFFICULTY_BEGINNER;
    }
}
