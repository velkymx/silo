<?php

namespace App\Http\Controllers;

use App\Services\Dashboard\DashboardService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The home screen: a fast, honest "where do I continue?" landing surface.
 * Delegates every data question to DashboardService and renders a single
 * Inertia page whose props map one-to-one to the home-screen cards.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(): Response
    {
        $user = auth()->user();

        return Inertia::render('Dashboard/Index', [
            'jumpBackIn' => $this->dashboard->jumpBackIn($user)?->toArray(),
            'continueWorking' => $this->dashboard->continueWorking($user),
        ]);
    }
}
