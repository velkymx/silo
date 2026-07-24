<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\User;
use App\Services\Rss\OpmlExporter;
use App\Services\Rss\OpmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpmlExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_routes_to_opml_download(): void
    {
        $user = User::factory()->create();
        RssFeed::factory()->for($user)->create(['title' => 'Foo', 'url' => 'https://foo/feed']);

        $response = $this->actingAs($user)->get('/rss/opml/export');

        $response->assertOk();
        $this->assertStringStartsWith('text/x-opml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('subscriptions-', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Foo', $response->streamedContent());
    }

    public function test_export_excludes_muted_feeds(): void
    {
        $user = User::factory()->create();
        RssFeed::factory()->for($user)->create(['title' => 'Visible', 'url' => 'https://v/feed']);
        $muted = RssFeed::factory()->for($user)->create(['title' => 'Muted', 'url' => 'https://m/feed']);
        $muted->mute();

        $response = $this->actingAs($user)->get('/rss/opml/export');

        $response->assertOk();
        $this->assertStringContainsString('Visible', $response->streamedContent());
        $this->assertStringNotContainsString('Muted', $response->streamedContent());
    }

    public function test_export_excludes_other_users_feeds(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        RssFeed::factory()->for($me)->create(['title' => 'Mine', 'url' => 'https://mine/feed']);
        RssFeed::factory()->for($other)->create(['title' => 'Theirs', 'url' => 'https://theirs/feed']);

        $response = $this->actingAs($me)->get('/rss/opml/export');

        $this->assertStringContainsString('Mine', $response->streamedContent());
        $this->assertStringNotContainsString('Theirs', $response->streamedContent());
    }

    public function test_round_trip_export_then_parse_yields_same_feeds(): void
    {
        $user = User::factory()->create();
        RssFeed::factory()->for($user)->create(['title' => 'A', 'url' => 'https://a/feed', 'folder' => 'Tech']);
        RssFeed::factory()->for($user)->create(['title' => 'B', 'url' => 'https://b/feed', 'folder' => 'Tech']);
        RssFeed::factory()->for($user)->create(['title' => 'C', 'url' => 'https://c/feed', 'folder' => null]);

        $feeds = RssFeed::ownedBy($user->id)->whereNull('muted_at')->get();
        $xml = (new OpmlExporter)->build($feeds);

        $entries = (new OpmlParser)->parse($xml);

        $this->assertCount(3, $entries);
        $byUrl = collect($entries)->keyBy('url');
        $this->assertSame('A', $byUrl['https://a/feed']['title']);
        $this->assertSame('Tech', $byUrl['https://a/feed']['folder']);
        $this->assertSame('C', $byUrl['https://c/feed']['title']);
        $this->assertNull($byUrl['https://c/feed']['folder']);
    }

    public function test_export_escapes_xml_special_characters_in_title(): void
    {
        $user = User::factory()->create();
        RssFeed::factory()->for($user)->create(['title' => 'A & B <foo> "bar"', 'url' => 'https://x/feed']);

        $feeds = RssFeed::ownedBy($user->id)->get();
        $xml = (new OpmlExporter)->build($feeds);

        $this->assertStringContainsString('A &amp; B &lt;foo&gt; &quot;bar&quot;', $xml);
        $this->assertStringNotContainsString('A & B <foo>', $xml);
    }
}
