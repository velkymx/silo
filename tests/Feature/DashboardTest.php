<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\File;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DashboardService
    {
        return app(DashboardService::class);
    }

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_renders_with_a_jump_back_in_prop(): void
    {
        $this->asUser();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->has('jumpBackIn'));
    }

    public function test_jump_back_in_is_the_most_recently_content_edited_file(): void
    {
        $user = $this->asUser();

        $this->withFile($user, ['name' => 'Old.txt', 'content_edited_at' => now()->subDays(3)]);
        $recent = $this->withFile($user, ['name' => 'Fresh.txt', 'content_edited_at' => now()->subMinutes(5)]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('jumpBackIn.id', $recent->id)
            ->where('jumpBackIn.title', 'Fresh.txt')
            ->where('jumpBackIn.type', 'file'));
    }

    public function test_jump_back_in_links_a_markdown_note_to_the_notes_surface(): void
    {
        $user = $this->asUser();

        $note = $this->withFile($user, [
            'name' => 'Journal',
            'mime' => 'text/markdown',
            'content_edited_at' => now(),
        ]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('jumpBackIn.id', $note->id)
            ->where('jumpBackIn.type', 'note')
            ->where('jumpBackIn.url', route('notes.index', ['open' => $note->id])));
    }

    public function test_jump_back_in_ignores_never_edited_and_other_users_files(): void
    {
        $user = $this->asUser();
        $other = User::factory()->create();

        $this->withFile($user, ['content_edited_at' => null]);
        $this->withFile($other, ['content_edited_at' => now()]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('jumpBackIn', null));
    }

    public function test_dashboard_exposes_a_continue_working_prop(): void
    {
        $this->asUser();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('continueWorking'));
    }

    public function test_continue_working_mixes_modules_newest_first_and_caps_at_six(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id]);

        $this->withFile($user, ['name' => 'Proposal.md', 'mime' => 'text/markdown', 'content_edited_at' => now()->subMinutes(5)]);
        Bookmark::factory()->create(['owner_id' => $user->id, 'title' => 'Portal', 'category' => null, 'created_at' => now()->subMinutes(10)]);
        RssItem::factory()->create(['user_id' => $user->id, 'feed_id' => $feed->id, 'title' => 'Release Notes', 'read_at' => now()->subMinutes(15)]);
        // Seven edited files older than the above — proves the cap and ordering.
        foreach (range(1, 7) as $i) {
            $this->withFile($user, ['name' => "Old{$i}.txt", 'content_edited_at' => now()->subHours($i + 1)]);
        }

        $items = $this->service()->continueWorking($user);

        $this->assertCount(6, $items);
        $this->assertSame('Proposal.md', $items[0]['title']);
        $this->assertSame('note', $items[0]['type']);
        $this->assertSame('edited', $items[0]['reason']);
        $this->assertSame('Portal', $items[1]['title']);
        $this->assertSame('bookmark', $items[1]['type']);
        $this->assertSame('added', $items[1]['reason']);
        $this->assertSame('Release Notes', $items[2]['title']);
        $this->assertSame('article', $items[2]['type']);
        $this->assertSame('read', $items[2]['reason']);
    }

    public function test_continue_working_only_lists_uncategorized_bookmarks(): void
    {
        $user = User::factory()->create();
        Bookmark::factory()->create(['owner_id' => $user->id, 'title' => 'Filed', 'category' => 'Docs']);
        Bookmark::factory()->create(['owner_id' => $user->id, 'title' => 'Loose', 'category' => null]);

        $titles = collect($this->service()->continueWorking($user))->pluck('title');

        $this->assertTrue($titles->contains('Loose'));
        $this->assertFalse($titles->contains('Filed'));
    }

    public function test_continue_working_only_lists_read_articles_and_is_user_scoped(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id]);
        $otherFeed = RssFeed::factory()->create(['user_id' => $other->id]);

        RssItem::factory()->create(['user_id' => $user->id, 'feed_id' => $feed->id, 'title' => 'ReadMine', 'read_at' => now()]);
        RssItem::factory()->create(['user_id' => $user->id, 'feed_id' => $feed->id, 'title' => 'UnreadMine', 'read_at' => null]);
        RssItem::factory()->create(['user_id' => $other->id, 'feed_id' => $otherFeed->id, 'title' => 'ReadTheirs', 'read_at' => now()]);

        $titles = collect($this->service()->continueWorking($user))->pluck('title');

        $this->assertTrue($titles->contains('ReadMine'));
        $this->assertFalse($titles->contains('UnreadMine'));
        $this->assertFalse($titles->contains('ReadTheirs'));
    }

    public function test_dashboard_exposes_a_whats_new_prop(): void
    {
        $this->asUser();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('whatsNew'));
    }

    public function test_whats_new_is_null_when_nothing_is_unread(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id]);
        RssItem::factory()->create(['user_id' => $user->id, 'feed_id' => $feed->id, 'is_read' => true, 'read_at' => now()]);

        $this->assertNull($this->service()->whatsNew($user));
    }

    public function test_whats_new_reports_unread_count_and_newest_articles(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id, 'title' => 'Laravel News']);
        $otherFeed = RssFeed::factory()->create(['user_id' => $other->id]);

        foreach (range(1, 7) as $i) {
            RssItem::factory()->create([
                'user_id' => $user->id, 'feed_id' => $feed->id,
                'title' => "Article {$i}", 'is_read' => false, 'read_at' => null,
                'published_at' => now()->subMinutes($i),
            ]);
        }
        // Read + other-user items must not count.
        RssItem::factory()->create(['user_id' => $user->id, 'feed_id' => $feed->id, 'is_read' => true, 'read_at' => now()]);
        RssItem::factory()->create(['user_id' => $other->id, 'feed_id' => $otherFeed->id, 'is_read' => false, 'read_at' => null]);

        $whatsNew = $this->service()->whatsNew($user);

        $this->assertNotNull($whatsNew);
        $data = $whatsNew->toArray();
        $this->assertSame(7, $data['unreadCount']);
        $this->assertCount(5, $data['articles']);
        $this->assertSame('Article 1', $data['articles'][0]['title']);
        $this->assertSame('Laravel News', $data['articles'][0]['feed']);
        $this->assertSame(route('rss.items.show', ['item' => $data['articles'][0]['id']]), $data['articles'][0]['url']);
        $this->assertSame(route('rss.index'), $data['inboxUrl']);
    }

    public function test_dashboard_exposes_a_needs_attention_prop(): void
    {
        $this->asUser();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('needsAttention'));
    }

    public function test_needs_attention_is_empty_when_all_is_well(): void
    {
        $user = User::factory()->create();
        $this->withFile($user, ['status' => File::STATUS_READY]);

        $this->assertSame([], $this->service()->needsAttention($user));
    }

    public function test_needs_attention_flags_infected_files_as_red(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->withFile($user, ['status' => File::STATUS_INFECTED]);
        $this->withFile($other, ['status' => File::STATUS_INFECTED]);

        $items = $this->service()->needsAttention($user);

        $this->assertCount(1, $items);
        $this->assertSame('red', $items[0]['tier']);
        $this->assertStringContainsStringIgnoringCase('virus', $items[0]['title']);
        $this->assertSame(route('files.index'), $items[0]['url']);
    }

    public function test_needs_attention_flags_high_storage_as_yellow(): void
    {
        config(['filemanager.user_quota_mb' => 1]);
        $user = User::factory()->create();
        $this->withFile($user, ['size' => (int) (0.9 * 1024 * 1024)]);

        $items = $this->service()->needsAttention($user);

        $this->assertCount(1, $items);
        $this->assertSame('yellow', $items[0]['tier']);
        $this->assertStringContainsStringIgnoringCase('storage', $items[0]['title']);
    }

    public function test_needs_attention_orders_red_then_yellow_then_blue(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id]);

        // Red: a failing feed.
        RssFeed::factory()->create(['user_id' => $user->id, 'consecutive_failures' => 5]);
        // Yellow: a dead bookmark.
        Bookmark::factory()->create(['owner_id' => $user->id, 'status' => Bookmark::STATUS_DEAD]);
        // Blue: a healthy but stale feed.
        $feed->update(['consecutive_failures' => 0, 'last_success_at' => now()->subDays(10), 'last_fetched_at' => now()->subDays(10)]);

        $tiers = collect($this->service()->needsAttention($user))->pluck('tier')->all();

        $this->assertSame(['red', 'yellow', 'blue'], $tiers);
    }

    public function test_system_health_is_present_for_admins(): void
    {
        $this->asAdmin();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('systemHealth')
                ->has('systemHealth.attentionCount')
                ->has('systemHealth.facts'));
    }

    public function test_system_health_is_hidden_from_non_admins(): void
    {
        $this->asUser();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('systemHealth', null));
    }
}
