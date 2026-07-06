<?php

namespace Tests\Feature\Rss;

use App\Jobs\Rss\ImportOpml;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OpmlImportUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_url_endpoint_fetches_and_queues_import(): void
    {
        Queue::fake();
        Http::fake([
            'feedly.com/*' => Http::response($this->opmlFixture(), 200, ['Content-Type' => 'text/x-opml']),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/rss/opml/import-url', ['url' => 'https://feedly.com/export/opml'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(ImportOpml::class, fn (ImportOpml $job) => $job->userId === $user->id && str_contains($job->opml, 'Laravel News'));
    }

    public function test_url_endpoint_returns_error_on_http_failure(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'feedly.com/*' => Http::response('', 404),
        ]);

        $this->actingAs($user)
            ->post('/rss/opml/import-url', ['url' => 'https://feedly.com/export/opml'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_url_endpoint_validates_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/rss/opml/import-url', ['url' => 'not-a-url'])
            ->assertSessionHasErrors('url');
    }

    public function test_url_endpoint_rejects_oversized_response(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'big.example/*' => Http::response(str_repeat('a', 6 * 1024 * 1024), 200),
        ]);

        $this->actingAs($user)
            ->post('/rss/opml/import-url', ['url' => 'https://big.example/opml'])
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    private function opmlFixture(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <body>
    <outline type="rss" text="Laravel News" xmlUrl="https://laravel-news.com/feed.xml"/>
  </body>
</opml>
XML;
    }
}
