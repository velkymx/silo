<?php

namespace Tests\Feature;

use App\Jobs\Automation\Subscribers\RecordActivity;
use App\Models\AuditLog;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_rss_item_activity_does_not_touch_file_columns(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->for($feed, 'feed')->for($user)->create();

        (new RecordActivity(
            $user->id,
            'rss.item.created',
            $item->id,
            RssItem::class,
            ['feed_id' => $feed->id, 'title' => $item->title, 'url' => $item->url],
            now(),
        ))->handle();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'rss.item.created',
            'file_id' => null,
            'file_name' => null,
        ]);

        $log = AuditLog::where('action', 'rss.item.created')->firstOrFail();
        $this->assertSame(RssItem::class, $log->meta['entity_type']);
        $this->assertSame($item->id, $log->meta['entity_id']);
        $this->assertSame($feed->id, $log->meta['feed_id']);
    }
}
