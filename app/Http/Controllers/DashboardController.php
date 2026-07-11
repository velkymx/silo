<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use App\Services\Health\HealthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home screen: a fast, honest "where do I continue?" landing surface.
 * Delegates every data question to DashboardService and renders a single
 * Inertia page whose props map one-to-one to the home-screen cards.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly HealthService $health,
    ) {}

    public function index(Request $request): Response
    {
        $user = auth()->user();

        // The per-user cards are briefly cached: the home screen is the first
        // thing hit after login and on every "Home" click, and 30-60s of
        // staleness is invisible to the user. System Health stays live — an
        // operator wants current numbers, and it is admin-only, low-traffic.
        $cards = Cache::remember("dashboard.{$user->id}", 45, fn () => [
            'jumpBackIn' => $this->dashboard->jumpBackIn($user)?->toArray(),
            'continueWorking' => $this->dashboard->continueWorking($user),
            // Compact on the home screen: the card shows the count plus the
            // newest headline only, so one article is all it needs.
            'whatsNew' => $this->dashboard->whatsNew($user, 1)?->toArray(),
            'needsAttention' => $this->dashboard->needsAttention($user),
        ]);

        return Inertia::render('Dashboard/Index', $cards + [
            // Operator-only: the System Health card is gated to admins.
            'systemHealth' => $user->is_admin ? $this->health->cardSummary() : null,
            // Session state, never cached: true while today's Daily Word game
            // is unfinished ("Daily Word is waiting for you").
            'dailyWord' => ! (bool) data_get(
                $request->session()->get('dwg.'.now()->format('Y-m-d')),
                'gameOver',
                false,
            ),
            // The shared dashboard wall — outside the card cache so a new post
            // shows up the moment it is made.
            'wall' => WallController::latest(null, $user),
        ]);
    }
}
