<?php

namespace App\Http\Controllers;

use App\Services\DailyWordGame;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DailyWordGameController extends Controller
{
    public function index(Request $request, DailyWordGame $game): \Inertia\Response
    {
        $today = Carbon::today();
        $dateKey = $today->format('Y-m-d');
        $state = $request->session()->get("dwg.{$dateKey}", [
            'guesses' => [],
            'statuses' => [],
            'gameOver' => false,
            'won' => false,
        ]);

        $target = null;
        if ($state['gameOver']) {
            $target = $game->targetForDate($today);
        }

        return Inertia::render('Break/Dwg', [
            'date' => $dateKey,
            'wordLength' => $game->wordLength(),
            'maxGuesses' => $game->maxGuesses(),
            'guesses' => $state['guesses'],
            'statuses' => $state['statuses'],
            'gameOver' => $state['gameOver'],
            'won' => $state['won'],
            'target' => $target,
        ]);
    }

    public function guess(Request $request, DailyWordGame $game): JsonResponse
    {
        $request->validate([
            'word' => ['required', 'string', 'size:'.$game->wordLength(), 'alpha'],
        ]);

        $today = Carbon::today();
        $dateKey = $today->format('Y-m-d');
        $state = $request->session()->get("dwg.{$dateKey}", [
            'guesses' => [],
            'statuses' => [],
            'gameOver' => false,
            'won' => false,
        ]);

        if ($state['gameOver']) {
            return response()->json([
                'message' => 'The game is already over.',
                'gameOver' => true,
                'won' => $state['won'],
                'target' => $game->targetForDate($today),
            ], 422);
        }

        $guess = strtolower($request->input('word'));
        $target = $game->targetForDate($today);
        $statuses = $game->evaluate($guess, $target);

        $state['guesses'][] = $guess;
        $state['statuses'][] = $statuses;

        if ($guess === $target) {
            $state['won'] = true;
            $state['gameOver'] = true;
        } elseif (count($state['guesses']) >= $game->maxGuesses()) {
            $state['gameOver'] = true;
        }

        $request->session()->put("dwg.{$dateKey}", $state);

        return response()->json([
            'statuses' => $statuses,
            'guess' => $guess,
            'gameOver' => $state['gameOver'],
            'won' => $state['won'],
            'target' => $state['gameOver'] ? $target : null,
        ]);
    }
}
