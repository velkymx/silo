<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileServingTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_stream_raw_file_but_others_cannot(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Storage::disk('public')->put($path = 'uploads/'.$owner->id.'/a.txt', 'secret');
        $file = File::factory()->for($owner, 'owner')->create(['name' => 'a.txt', 'path' => $path]);

        $this->actingAs($owner)->get(route('files.raw', $file))->assertOk();
        $this->actingAs($other)->get(route('files.raw', $file))->assertForbidden();
    }

    public function test_raw_route_requires_authentication(): void
    {
        Storage::fake('public');
        $file = File::factory()->create();

        $this->get(route('files.raw', $file))->assertRedirect(route('login'));
    }

    public function test_file_listing_is_paginated(): void
    {
        $user = User::factory()->create();
        File::factory()->count(60)->for($user, 'owner')->create();

        $this->actingAs($user)->get('/')->assertInertia(
            fn (Assert $page) => $page
                ->where('pagination.total', 60)
                ->where('pagination.per_page', 50)
                ->where('pagination.last_page', 2)
                ->has('files', 50)
        );

        $this->actingAs($user)->get('/?page=2')->assertInertia(
            fn (Assert $page) => $page->where('pagination.current_page', 2)->has('files', 10)
        );
    }
}
