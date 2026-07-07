<?php

namespace Tests\Feature;

use App\Jobs\ProcessBookmark;
use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_own_and_shared_bookmarks_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Bookmark::factory()->for($user, 'owner')->create(['title' => 'Mine']);
        Bookmark::factory()->for($other, 'owner')->shared()->create(['title' => 'Company']);
        Bookmark::factory()->for($other, 'owner')->create(['title' => 'Theirs']);

        $this->actingAs($user)->get(route('bookmarks.index'))
            ->assertInertia(fn ($page) => $page->component('Bookmarks/Index')->has('bookmarks', 2));
    }

    public function test_index_search_filters_via_scout(): void
    {
        $user = User::factory()->create();
        Bookmark::factory()->for($user, 'owner')->create(['title' => 'Payroll Portal']);
        Bookmark::factory()->for($user, 'owner')->create(['title' => 'Engineering Wiki']);

        $this->actingAs($user)->get(route('bookmarks.index', ['search' => 'payroll']))
            ->assertInertia(fn ($page) => $page->has('bookmarks', 1)
                ->where('bookmarks.0.title', 'Payroll Portal'));
    }

    public function test_store_creates_a_bookmark_and_queues_processing(): void
    {
        Bus::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('bookmarks.store'), [
            'title' => 'Wiki', 'url' => 'https://wiki.example.com', 'category' => 'Docs',
        ])->assertRedirect();

        $this->assertDatabaseHas('bookmarks', ['title' => 'Wiki', 'owner_id' => $user->id]);
        Bus::assertDispatched(ProcessBookmark::class);
    }

    public function test_store_rejects_an_invalid_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('bookmarks.store'), ['title' => 'Bad', 'url' => 'not-a-url'])
            ->assertSessionHasErrors('url');
    }

    public function test_store_rejects_non_http_schemes(): void
    {
        $user = User::factory()->create();
        foreach (['javascript:alert(1)', 'file:///etc/passwd', 'ftp://example.com'] as $url) {
            $this->actingAs($user)->post(route('bookmarks.store'), ['title' => 'Bad', 'url' => $url])
                ->assertSessionHasErrors('url');
        }
    }

    public function test_import_creates_bookmarks_and_queues_processing(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        Bookmark::factory()->for($user, 'owner')->create(['url' => 'https://dupe.com']);
        $html = '<DL><DT><A HREF="https://a.com">A</A><DT><H3>Work</H3>'
            .'<DL><DT><A HREF="https://b.com">B</A></DL><DT><A HREF="https://dupe.com">Dupe</A></DL>';
        $file = UploadedFile::fake()->createWithContent('bookmarks.html', $html);

        $this->actingAs($user)->post(route('bookmarks.import'), ['file' => $file])->assertRedirect();

        $this->assertDatabaseHas('bookmarks', ['url' => 'https://a.com', 'category' => null]);
        $this->assertDatabaseHas('bookmarks', ['url' => 'https://b.com', 'category' => 'Work']);
        // Existing URL not duplicated.
        $this->assertSame(1, Bookmark::where('owner_id', $user->id)->where('url', 'https://dupe.com')->count());
        Bus::assertDispatched(ProcessBookmark::class, 2);
    }

    public function test_go_increments_clicks_and_redirects(): void
    {
        $user = User::factory()->create();
        $bookmark = Bookmark::factory()->for($user, 'owner')->create(['url' => 'https://tool.example.com', 'click_count' => 4]);

        $this->actingAs($user)->get(route('bookmarks.go', $bookmark))->assertRedirect('https://tool.example.com');

        $this->assertSame(5, $bookmark->fresh()->click_count);
    }

    public function test_owner_can_update_and_delete(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        $bookmark = Bookmark::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->put(route('bookmarks.update', $bookmark), [
            'title' => 'Renamed', 'url' => 'https://x.example.com',
        ])->assertRedirect();
        $this->assertSame('Renamed', $bookmark->fresh()->title);

        $this->actingAs($user)->delete(route('bookmarks.destroy', $bookmark))->assertRedirect();
        $this->assertModelMissing($bookmark);
    }

    public function test_non_owner_cannot_update_or_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bookmark = Bookmark::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)->put(route('bookmarks.update', $bookmark), [
            'title' => 'Hijack', 'url' => 'https://x.example.com',
        ])->assertForbidden();
        $this->actingAs($other)->delete(route('bookmarks.destroy', $bookmark))->assertForbidden();
    }

    public function test_dedup_removes_duplicate_urls_keeping_earliest(): void
    {
        $user = User::factory()->create();
        $keep = Bookmark::factory()->for($user, 'owner')->create(['url' => 'https://same.com']);
        Bookmark::factory()->for($user, 'owner')->create(['url' => 'https://same.com']);
        Bookmark::factory()->for($user, 'owner')->create(['url' => 'https://unique.com']);

        $this->actingAs($user)->post(route('bookmarks.dedup'))->assertRedirect();

        $this->assertSame(2, Bookmark::where('owner_id', $user->id)->count());
        $this->assertModelExists($keep);
    }

    public function test_star_toggles(): void
    {
        $user = User::factory()->create();
        $bookmark = Bookmark::factory()->for($user, 'owner')->create(['starred' => false]);

        $this->actingAs($user)->post(route('bookmarks.star', $bookmark))->assertRedirect();
        $this->assertTrue($bookmark->fresh()->starred);

        $this->actingAs($user)->post(route('bookmarks.star', $bookmark))->assertRedirect();
        $this->assertFalse($bookmark->fresh()->starred);
    }

    public function test_non_owner_cannot_star(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bookmark = Bookmark::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)->post(route('bookmarks.star', $bookmark))->assertForbidden();
    }

    public function test_prune_removes_dead_bookmarks_only(): void
    {
        $user = User::factory()->create();
        $dead = Bookmark::factory()->for($user, 'owner')->create(['status' => 'dead']);
        $alive = Bookmark::factory()->for($user, 'owner')->create(['status' => 'alive']);

        $this->actingAs($user)->post(route('bookmarks.prune'))->assertRedirect();

        $this->assertModelMissing($dead);
        $this->assertModelExists($alive);
    }

    public function test_validate_all_queues_a_job_per_bookmark(): void
    {
        Bus::fake();
        $user = User::factory()->create();
        Bookmark::factory()->count(3)->for($user, 'owner')->create();

        $this->actingAs($user)->post(route('bookmarks.validate'))->assertRedirect();

        Bus::assertDispatched(ProcessBookmark::class, 3);
    }

    public function test_cannot_open_a_private_bookmark_of_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bookmark = Bookmark::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)->get(route('bookmarks.go', $bookmark))->assertForbidden();
    }
}
