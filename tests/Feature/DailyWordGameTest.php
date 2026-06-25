<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DailyWordGame;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyWordGameTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_redirects_guests(): void
    {
        $this->get('/break/dwg')->assertRedirect('/login');
    }

    public function test_page_renders_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/break/dwg')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Break/Dwg')
                ->where('wordLength', 5)
                ->where('maxGuesses', 6)
                ->where('gameOver', false)
            );
    }

    public function test_guess_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/break/dwg/guess', ['word' => 'ab'])
            ->assertUnprocessable();

        $this->actingAs($user)
            ->postJson('/break/dwg/guess', ['word' => '12345'])
            ->assertUnprocessable();
    }

    public function test_correct_guess_wins_game(): void
    {
        $user = User::factory()->create();
        $target = app(DailyWordGame::class)->targetForDate(Carbon::today());

        $response = $this->actingAs($user)
            ->postJson('/break/dwg/guess', ['word' => $target]);

        $response->assertOk()
            ->assertJsonPath('won', true)
            ->assertJsonPath('gameOver', true)
            ->assertJsonPath('target', $target)
            ->assertJsonPath('statuses', ['correct', 'correct', 'correct', 'correct', 'correct']);
    }

    public function test_wrong_guess_tracks_statuses_and_guesses(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/break/dwg/guess', ['word' => 'apple']);

        $response->assertOk()
            ->assertJsonPath('won', false)
            ->assertJsonPath('gameOver', false)
            ->assertJsonPath('target', null)
            ->assertJsonPath('guess', 'apple')
            ->assertJsonStructure(['statuses' => []]);
    }

    public function test_game_over_after_max_guesses(): void
    {
        $user = User::factory()->create();
        $target = app(DailyWordGame::class)->targetForDate(Carbon::today());
        $wrongWord = $target === 'apple' ? 'beach' : 'apple';

        for ($i = 0; $i < 5; $i += 1) {
            $this->actingAs($user)
                ->postJson('/break/dwg/guess', ['word' => $wrongWord])
                ->assertOk()
                ->assertJsonPath('gameOver', false);
        }

        $this->actingAs($user)
            ->postJson('/break/dwg/guess', ['word' => $wrongWord])
            ->assertOk()
            ->assertJsonPath('gameOver', true)
            ->assertJsonPath('won', false)
            ->assertJsonPath('target', $target);
    }

    public function test_cannot_guess_after_game_over(): void
    {
        $user = User::factory()->create();
        $target = app(DailyWordGame::class)->targetForDate(Carbon::today());
        $wrongWord = $target === 'apple' ? 'beach' : 'apple';

        for ($i = 0; $i < 6; $i += 1) {
            $this->actingAs($user)
                ->postJson('/break/dwg/guess', ['word' => $wrongWord]);
        }

        $this->actingAs($user)
            ->postJson('/break/dwg/guess', ['word' => $wrongWord])
            ->assertStatus(422)
            ->assertJsonPath('message', 'The game is already over.');
    }

    public function test_progress_persists_in_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/break/dwg/guess', ['word' => 'apple'])
            ->assertOk();

        $this->actingAs($user)
            ->get('/break/dwg')
            ->assertInertia(fn ($page) => $page
                ->where('guesses.0', 'apple')
                ->where('gameOver', false)
            );
    }
}
