<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\Engine;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Muting a feed must pull its items out of the Scout index (and unmuting
 * must put them back), because shouldBeSearchable() otherwise only takes
 * effect on the next item save. We assert against a spy Scout engine —
 * the model's searchable()/unsearchable() are index writers, not query
 * scopes, so they can only be verified at the engine boundary.
 */
class MuteSyncIndexTest extends TestCase
{
    use RefreshDatabase;

    private function spyScoutEngine(): MockInterface
    {
        $engine = Mockery::spy(Engine::class);
        app(EngineManager::class)->extend('spy', fn () => $engine);
        config(['scout.driver' => 'spy']);

        return $engine;
    }

    public function test_muting_a_feed_removes_its_items_from_the_index(): void
    {
        $engine = $this->spyScoutEngine();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(3)->create(['feed_id' => $feed->id, 'user_id' => $user->id]);

        $feed->mute();

        $engine->shouldHaveReceived('delete');
    }

    public function test_unmuting_a_feed_re_adds_its_items_to_the_index(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['muted_at' => now()]);
        RssItem::factory()->count(3)->create(['feed_id' => $feed->id, 'user_id' => $user->id]);

        // Start spying after setup so we observe only the unmute's writes.
        $engine = $this->spyScoutEngine();
        $feed->unmute();

        $engine->shouldHaveReceived('update');
    }

    public function test_already_muted_mute_is_a_no_op(): void
    {
        $engine = $this->spyScoutEngine();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['muted_at' => now()]);

        $this->assertFalse($feed->mute());
        $engine->shouldNotHaveReceived('delete');
    }
}
