<?php

namespace Tests\Feature\Rss;

use App\Jobs\Rss\ImportOpml;
use App\Jobs\Rss\RefreshFeed;
use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OpmlImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_queues_import_job_with_xml_payload(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $opml = $this->opmlFixture();

        $this->actingAs($user)
            ->post('/rss/opml/import', ['opml' => UploadedFile::fake()->createWithContent('subs.xml', $opml, 'text/xml')])
            ->assertRedirect()
            ->assertSessionHas('success');

        Queue::assertPushed(ImportOpml::class, fn (ImportOpml $job) => $job->userId === $user->id && str_contains($job->opml, 'Laravel News'));
    }

    public function test_job_creates_feeds_and_dispatches_refresh(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $opml = $this->opmlFixture();

        (new ImportOpml($user->id, $opml))->handle(app(\App\Services\Rss\OpmlParser::class));

        $this->assertSame(2, RssFeed::where('user_id', $user->id)->count());
        $laravel = RssFeed::where('user_id', $user->id)->where('title', 'Laravel News')->first();
        $this->assertNotNull($laravel);
        $this->assertSame('Tech', $laravel->folder);

        Queue::assertPushed(RefreshFeed::class, 2);
    }

    public function test_job_is_idempotent_on_repeat(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $opml = $this->opmlFixture();

        $job = new ImportOpml($user->id, $opml);
        $job->handle(app(\App\Services\Rss\OpmlParser::class));
        $job->handle(app(\App\Services\Rss\OpmlParser::class));

        $this->assertSame(2, RssFeed::where('user_id', $user->id)->count());
    }

    public function test_non_http_urls_are_rejected(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $opml = <<<'XML'
<?xml version="1.0"?><opml version="2.0"><body>
  <outline type="rss" text="Bad" xmlUrl="javascript:alert(1)"/>
  <outline type="rss" text="Good" xmlUrl="https://example.com/feed"/>
</body></opml>
XML;

        (new ImportOpml($user->id, $opml))->handle(app(\App\Services\Rss\OpmlParser::class));

        $this->assertSame(1, RssFeed::where('user_id', $user->id)->count());
    }

    public function test_import_caps_the_number_of_feeds_created(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $outlines = '';
        for ($i = 0; $i < ImportOpml::MAX_ENTRIES + 25; $i++) {
            $outlines .= '<outline type="rss" text="Feed '.$i.'" xmlUrl="https://example.com/feed'.$i.'.xml"/>';
        }
        $opml = '<?xml version="1.0"?><opml version="2.0"><body>'.$outlines.'</body></opml>';

        (new ImportOpml($user->id, $opml))->handle(app(\App\Services\Rss\OpmlParser::class));

        $this->assertSame(ImportOpml::MAX_ENTRIES, RssFeed::where('user_id', $user->id)->count());
    }

    public function test_upload_requires_file(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/rss/opml/import', [])
            ->assertSessionHasErrors('opml');
    }

    public function test_guest_cannot_import(): void
    {
        $this->post('/rss/opml/import', ['opml' => UploadedFile::fake()->create('x.xml', 1, 'text/xml')])
            ->assertRedirect('/login');
    }

    private function opmlFixture(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head><title>Subs</title></head>
  <body>
    <outline text="Tech">
      <outline type="rss" text="Laravel News" xmlUrl="https://laravel-news.com/feed.xml"/>
    </outline>
    <outline type="rss" text="PHP Weekly" xmlUrl="https://phpweekly.example/feed"/>
  </body>
</opml>
XML;
    }
}
