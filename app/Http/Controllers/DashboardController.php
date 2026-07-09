<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use App\Services\Health\HealthService;
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

    public function index(): Response
    {
        $user = auth()->user();

        // The per-user cards are briefly cached: the home screen is the first
        // thing hit after login and on every "Home" click, and 30-60s of
        // staleness is invisible to the user. System Health stays live — an
        // operator wants current numbers, and it is admin-only, low-traffic.
        $cards = Cache::remember("dashboard.{$user->id}", 45, fn () => [
            'jumpBackIn' => $this->dashboard->jumpBackIn($user)?->toArray(),
            'continueWorking' => $this->dashboard->continueWorking($user),
            'whatsNew' => $this->dashboard->whatsNew($user)?->toArray(),
            'needsAttention' => $this->dashboard->needsAttention($user),
        ]);

        return Inertia::render('Dashboard/Index', $cards + [
            // Operator-only: the System Health card is gated to admins.
            'systemHealth' => $user->is_admin ? $this->health->cardSummary() : null,
        ]);
    }
}
