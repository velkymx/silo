<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WallPost;
use App\Models\WallReaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WallTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_posts_to_the_dashboard_wall(): void
    {
        $user = $this->asUser();

        $this->post('/wall', ['body' => '<p>Hello <strong>world</strong></p>'])
            ->assertRedirect();

        $this->assertDatabaseHas('wall_posts', [
            'wall_user_id' => null,
            'author_id' => $user->id,
        ]);
        $this->assertStringContainsString('<strong>world</strong>', WallPost::first()->body);
    }

    public function test_a_user_posts_to_another_users_profile_wall(): void
    {
        $this->asUser();
        $target = User::factory()->create();

        $this->post('/wall', ['body' => '<p>Nice profile</p>', 'wall_user_id' => $target->id])
            ->assertRedirect();

        $this->assertDatabaseHas('wall_posts', ['wall_user_id' => $target->id]);
    }

    public function test_script_tags_are_stripped_at_store(): void
    {
        $this->asUser();

        $this->post('/wall', ['body' => '<p>hi</p><script>alert(1)</script>'])
            ->assertRedirect();

        $this->assertStringNotContainsString('<script', WallPost::first()->body);
        $this->assertStringContainsString('hi', WallPost::first()->body);
    }

    public function test_a_post_that_sanitizes_to_nothing_is_rejected(): void
    {
        $this->asUser();

        $this->post('/wall', ['body' => '<script>alert(1)</script>'])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, WallPost::count());
    }

    public function test_delete_matrix(): void
    {
        $author = User::factory()->create();
        $wallOwner = User::factory()->create();
        $bystander = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        $make = fn () => WallPost::factory()->onWallOf($wallOwner)->create(['author_id' => $author->id]);

        // Bystander: no.
        $post = $make();
        $this->actingAs($bystander)->delete("/wall/{$post->id}")->assertForbidden();

        // Author: yes.
        $this->actingAs($author)->delete("/wall/{$post->id}")->assertRedirect();
        $this->assertDatabaseMissing('wall_posts', ['id' => $post->id]);

        // Wall owner: yes.
        $post = $make();
        $this->actingAs($wallOwner)->delete("/wall/{$post->id}")->assertRedirect();
        $this->assertDatabaseMissing('wall_posts', ['id' => $post->id]);

        // Admin: yes.
        $post = $make();
        $this->actingAs($admin)->delete("/wall/{$post->id}")->assertRedirect();
        $this->assertDatabaseMissing('wall_posts', ['id' => $post->id]);
    }

    public function test_dashboard_wall_posts_cannot_be_deleted_by_non_authors(): void
    {
        $author = User::factory()->create();
        $bystander = User::factory()->create();
        $post = WallPost::factory()->create(['author_id' => $author->id]);

        $this->actingAs($bystander)->delete("/wall/{$post->id}")->assertForbidden();
    }

    public function test_reactions_toggle_and_reject_off_list_icons(): void
    {
        $user = $this->asUser();
        $post = WallPost::factory()->create();

        $this->post("/wall/{$post->id}/react", ['icon' => 'fire'])->assertRedirect();
        $this->assertDatabaseHas('wall_reactions', ['wall_post_id' => $post->id, 'user_id' => $user->id, 'icon' => 'fire']);

        $this->post("/wall/{$post->id}/react", ['icon' => 'fire'])->assertRedirect();
        $this->assertDatabaseMissing('wall_reactions', ['wall_post_id' => $post->id, 'user_id' => $user->id]);

        $this->post("/wall/{$post->id}/react", ['icon' => 'skull'])->assertSessionHasErrors('icon');
    }

    public function test_dashboard_ships_the_wall_prop_with_reactions(): void
    {
        $user = $this->asUser();
        $post = WallPost::factory()->create(['body' => '<p>on the board</p>']);
        WallReaction::create(['wall_post_id' => $post->id, 'user_id' => $user->id, 'icon' => 'fire', 'created_at' => now()]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->has('wall', 1)
            ->where('wall.0.body', '<p>on the board</p>')
            ->where('wall.0.reactions.0.icon', 'fire')
            ->where('wall.0.reactions.0.count', 1)
            ->where('wall.0.reactions.0.mine', true));
    }

    public function test_profile_page_renders_person_and_their_wall(): void
    {
        $this->asUser();
        $target = User::factory()->create(['name' => 'Wall Owner']);
        WallPost::factory()->onWallOf($target)->create(['body' => '<p>profile post</p>']);
        WallPost::factory()->create(); // dashboard post must not leak in

        $this->get("/directory/{$target->id}")->assertInertia(fn ($page) => $page
            ->component('Directory/Profile')
            ->where('person.name', 'Wall Owner')
            ->has('wall', 1)
            ->where('wall.0.body', '<p>profile post</p>'));
    }

    public function test_directory_card_endpoint_still_serves_the_pane(): void
    {
        $this->asUser();
        $target = User::factory()->create(['name' => 'Pane Person']);

        $this->getJson("/directory/{$target->id}/card")
            ->assertOk()
            ->assertJsonPath('person.name', 'Pane Person');
    }

    public function test_wall_json_pagination(): void
    {
        $this->asUser();
        WallPost::factory()->count(30)->create();

        $first = $this->getJson('/wall')->assertOk()->json();
        $this->assertCount(25, $first['posts']);
        $this->assertTrue($first['hasMore']);

        $oldest = collect($first['posts'])->last()['id'];
        $second = $this->getJson("/wall?before={$oldest}")->assertOk()->json();
        $this->assertCount(5, $second['posts']);
        $this->assertFalse($second['hasMore']);
    }

    public function test_a_wall_post_upsizes_into_a_note(): void
    {
        $user = $this->asUser();
        $author = User::factory()->create(['name' => 'Original Author']);
        $post = WallPost::factory()->create([
            'author_id' => $author->id,
            'body' => '<p>Great <strong>idea</strong> worth keeping</p>',
        ]);

        $response = $this->post("/wall/{$post->id}/upsize");

        $note = \App\Models\File::where('owner_id', $user->id)->where('mime', 'text/markdown')->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('Great idea worth keeping', $note->name);
        $response->assertRedirect(route('notes.index', ['open' => $note->id]));

        // Body converted to markdown + attribution; note owned by the actor.
        $content = \Illuminate\Support\Facades\Storage::disk($note->disk)->get($note->path);
        $this->assertStringContainsString('**idea**', $content);
        $this->assertStringContainsString('Original Author', $content);

        // The wall post is untouched.
        $this->assertDatabaseHas('wall_posts', ['id' => $post->id]);
    }

    public function test_body_length_is_capped(): void
    {
        $this->asUser();

        $this->post('/wall', ['body' => '<p>'.str_repeat('a', 5100).'</p>'])
            ->assertSessionHasErrors('body');
    }
}
