<?php

namespace Tests\Feature\Rss;

use App\Automation\AutomationDispatcher;
use App\Automation\Events\AutomationEvent;
use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use App\Models\Notification;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutomationDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_action_creates_row_for_user(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->for($feed, 'feed')->for($user)->create(['title' => 'Hello world']);
        $rule = AutomationRule::factory()->for($user)->create([
            'trigger_event' => 'rss.item.created',
            'conditions_json' => [],
            'actions_json' => [['type' => 'create_notification', 'data' => ['priority' => 'high']]],
        ]);

        $engine = app(AutomationDispatcher::class);
        $event = AutomationEvent::make('rss.item.created', $user->id, [
            'item_id' => $item->id, 'feed_id' => $feed->id, 'title' => $item->title, 'url' => $item->url,
        ]);
        $exec = $engine->evaluateRule($rule, $event);

        $this->assertSame('matched', $exec->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'source_id' => $item->id,
            'severity' => Notification::SEVERITY_HIGH,
        ]);
    }

    public function test_idempotency_prevents_double_execution(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->for($feed, 'feed')->for($user)->create();
        $rule = AutomationRule::factory()->for($user)->create([
            'trigger_event' => 'rss.item.created',
            'conditions_json' => [],
            'actions_json' => [['type' => 'create_notification']],
        ]);

        $engine = app(AutomationDispatcher::class);
        $event = AutomationEvent::make('rss.item.created', $user->id, [
            'item_id' => $item->id, 'feed_id' => $feed->id, 'title' => $item->title, 'url' => $item->url,
        ]);
        $first = $engine->evaluateRule($rule, $event);
        $second = $engine->evaluateRule($rule, $event);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Notification::where('user_id', $user->id)->count());
    }

    public function test_skipped_when_condition_fails(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->for($feed, 'feed')->for($user)->create();
        $rule = AutomationRule::factory()->for($user)->create([
            'trigger_event' => 'rss.item.created',
            'conditions_json' => ['title_contains' => 'this-string-is-absent'],
            'actions_json' => [['type' => 'create_notification']],
        ]);

        $engine = app(AutomationDispatcher::class);
        $event = AutomationEvent::make('rss.item.created', $user->id, [
            'item_id' => $item->id, 'feed_id' => $feed->id, 'title' => $item->title, 'url' => $item->url,
        ]);
        $exec = $engine->evaluateRule($rule, $event);

        $this->assertSame('skipped', $exec->status);
        $this->assertSame(0, Notification::where('user_id', $user->id)->count());
    }

    public function test_wildcard_trigger_matches_subscribed_event(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->for($feed, 'feed')->for($user)->create();
        $rule = AutomationRule::factory()->for($user)->create([
            'trigger_event' => 'rss.item.*',
            'conditions_json' => [],
            'actions_json' => [['type' => 'create_notification']],
        ]);

        $engine = app(AutomationDispatcher::class);
        $matched = $engine->rulesFor($user->id, 'rss.item.created');
        $unmatched = $engine->rulesFor($user->id, 'calendar.event.created');

        $this->assertCount(1, $matched);
        $this->assertCount(0, $unmatched);
    }

    public function test_save_bookmark_action_creates_a_row(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->for($feed, 'feed')->for($user)->create();
        $rule = AutomationRule::factory()->for($user)->create([
            'conditions_json' => [],
            'actions_json' => [['type' => 'save_bookmark', 'data' => ['category' => 'Research']]],
        ]);

        $engine = app(AutomationDispatcher::class);
        $event = AutomationEvent::make('rss.item.created', $user->id, [
            'item_id' => $item->id, 'feed_id' => $feed->id, 'title' => $item->title, 'url' => $item->url,
        ]);
        $engine->evaluateRule($rule, $event);

        $this->assertDatabaseHas('bookmarks', [
            'owner_id' => $user->id,
            'url' => $item->url,
            'category' => 'Research',
        ]);
    }

    public function test_mark_starred_action_toggles_item(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->for($feed, 'feed')->for($user)->create(['is_starred' => false]);
        $rule = AutomationRule::factory()->for($user)->create([
            'conditions_json' => [],
            'actions_json' => [['type' => 'mark_starred']],
        ]);

        $engine = app(AutomationDispatcher::class);
        $event = AutomationEvent::make('rss.item.created', $user->id, [
            'item_id' => $item->id, 'feed_id' => $feed->id, 'title' => $item->title, 'url' => $item->url,
        ]);
        $engine->evaluateRule($rule, $event);

        $item->refresh();
        $this->assertTrue($item->is_starred);
    }

    public function test_replay_endpoint_re_runs_rules_with_a_fresh_key(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->for($feed, 'feed')->for($user)->create();
        $rule = AutomationRule::factory()->for($user)->create([
            'conditions_json' => [],
            'actions_json' => [['type' => 'create_notification']],
        ]);
        $engine = app(AutomationDispatcher::class);
        $event = AutomationEvent::make('rss.item.created', $user->id, [
            'item_id' => $item->id, 'feed_id' => $feed->id, 'title' => $item->title, 'url' => $item->url,
        ]);
        $engine->evaluateRule($rule, $event);
        $this->assertSame(1, Notification::where('user_id', $user->id)->count());

        $execution = AutomationRuleExecution::first();
        $this->assertNotNull($execution);
        $this->assertSame($user->id, $execution->user_id, 'execution should belong to the test user');
        $response = $this->actingAs($user)
            ->withoutMiddleware([VerifyCsrfToken::class])
            ->post("/rss/rules/logs/{$execution->id}/replay");
        $response->assertRedirect();
        $this->assertSame(2, Notification::where('user_id', $user->id)->count());
    }
}
