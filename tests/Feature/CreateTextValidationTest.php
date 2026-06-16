<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateTextValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_content_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('files.text'), ['name' => 'note.md', 'content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('content');
    }

    public function test_non_empty_content_is_accepted(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('files.text'), ['name' => 'note.md', 'content' => '# Hello'])
            ->assertRedirect();
    }
}
