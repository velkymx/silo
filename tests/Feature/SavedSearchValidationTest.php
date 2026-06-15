<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSearchValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_param_types_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/saved-searches', [
            'name' => 'Bad',
            'params' => ['size_min' => 'not-a-number', 'tag' => ['nested']],
        ])->assertStatus(422)->assertJsonValidationErrors(['params.size_min', 'params.tag']);
    }

    public function test_valid_params_are_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/saved-searches', [
            'name' => 'Big PDFs',
            'params' => ['ftype' => 'pdf', 'size_min' => 1000, 'search' => 'report'],
        ])->assertRedirect();

        $this->assertDatabaseHas('saved_searches', ['owner_id' => $user->id, 'name' => 'Big PDFs']);
    }
}
