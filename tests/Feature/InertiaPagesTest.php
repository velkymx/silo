<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InertiaPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_inertia_login_page(): void
    {
        $this->get('/login')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Auth/Login')
        );
    }

    public function test_file_index_renders_inertia_with_contents(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Docs']);
        File::factory()->for($user, 'owner')->create(['name' => 'a.txt']);

        $this->actingAs($user)->get('/')->assertOk()->assertInertia(
            fn (Assert $page) => $page
                ->component('Files/Index')
                ->has('folders', 1)
                ->has('files', 1)
                ->where('files.0.name', 'a.txt')
        );
    }

    public function test_index_supplies_all_folders_for_the_tree(): void
    {
        $user = User::factory()->create();
        $root = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Root']);
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Child', 'parent_id' => $root->id]);

        $this->actingAs($user)->get('/')->assertInertia(
            fn (Assert $page) => $page
                ->has('allFolders', 2)
                ->where('allFolders.0.name', 'Child')
                ->where('allFolders.0.parent_id', $root->id)
                ->where('allFolders.1.name', 'Root')
                ->where('allFolders.1.parent_id', null)
        );
    }

    public function test_profile_page_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Profile/Edit')->where('user.email', $user->email)
        );
    }

    public function test_admin_users_page_requires_admin(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/users')->assertForbidden();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/users')->assertOk()->assertInertia(
            fn (Assert $page) => $page->component('Admin/Users/Index')->has('users', 2)
        );
    }
}
