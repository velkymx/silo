<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrimmedNameValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_whitespace_only_folder_name_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('folders.create'), ['folder_name' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('folder_name');
    }

    public function test_whitespace_only_rename_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['name' => 'a.txt']);

        $this->actingAs($user)
            ->patchJson(route('files.rename', $file), ['name' => "   \t  "])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }
}
