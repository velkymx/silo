<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Services\Health\HealthItem;
use App\Services\Health\HealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthServiceTest extends TestCase
{
    use RefreshDatabase;

    private function healthy(): void
    {
        // A baseline where nothing needs attention.
        config([
            'app.debug' => false,
            'app.url' => 'https://silo.test',
            'vault.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'silo.update_check.enabled' => false,
            // Backups go to a separate offsite-style disk with a recent run.
            'backup.disk' => 'local',
            'filemanager.disk' => 'public',
        ]);

        Backup::create([
            'disk' => 'local', 'filename' => 'b.zip', 'path' => 'backups/b.zip',
            'status' => Backup::STATUS_READY, 'checksum' => str_repeat('a', 64),
        ]);
    }

    private function service(): HealthService
    {
        return app(HealthService::class);
    }

    public function test_card_summary_is_all_clear_on_a_healthy_install(): void
    {
        $this->healthy();

        $summary = $this->service()->cardSummary();

        $this->assertSame(0, $summary['attentionCount']);
        $this->assertSame([], $summary['attention']);
        $this->assertCount(3, $summary['facts']);
    }

    public function test_debug_on_in_production_is_red(): void
    {
        $this->healthy();
        config(['app.debug' => true]);
        app()->detectEnvironment(fn () => 'production');

        $attention = collect($this->service()->cardSummary()['attention']);
        $debug = $attention->firstWhere('label', 'Debug mode');

        $this->assertNotNull($debug);
        $this->assertSame(HealthItem::RED, $debug['status']);
    }

    public function test_missing_vault_key_is_a_warning(): void
    {
        $this->healthy();
        config(['vault.key' => null]);

        $attention = collect($this->service()->cardSummary()['attention']);

        $this->assertNotNull($attention->firstWhere('label', 'Vault key'));
    }

    public function test_failed_jobs_are_flagged(): void
    {
        $this->healthy();
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database', 'queue' => 'default',
            'payload' => '{}', 'exception' => 'boom', 'failed_at' => now(),
        ]);

        $attention = collect($this->service()->cardSummary()['attention']);
        $failed = $attention->firstWhere('label', 'Failed jobs');

        $this->assertNotNull($failed);
        $this->assertSame(HealthItem::WARN, $failed['status']);
    }

    public function test_update_check_flags_a_newer_release_when_enabled(): void
    {
        $this->healthy();
        config(['silo.update_check.enabled' => true, 'silo.version' => '2.0.0']);
        Cache::flush();
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v2.3.0'])]);

        $update = $this->service()->updateCheck();

        $this->assertNotNull($update);
        $this->assertSame(HealthItem::WARN, $update->status);
        $this->assertStringContainsString('v2.3.0', $update->detail);
    }

    public function test_update_check_is_silent_when_disabled(): void
    {
        $this->healthy();
        config(['silo.update_check.enabled' => false]);
        Http::fake();

        $this->assertNull($this->service()->updateCheck());
        Http::assertNothingSent();
    }

    public function test_update_check_is_silent_when_up_to_date(): void
    {
        $this->healthy();
        config(['silo.update_check.enabled' => true, 'silo.version' => '2.3.0']);
        Cache::flush();
        Http::fake(['api.github.com/*' => Http::response(['tag_name' => 'v2.3.0'])]);

        $this->assertNull($this->service()->updateCheck());
    }

    public function test_missing_backup_is_flagged(): void
    {
        $this->healthy();
        Backup::query()->delete();

        $attention = collect($this->service()->cardSummary()['attention']);

        $this->assertNotNull($attention->firstWhere('label', 'Last backup'));
    }

    public function test_stale_backup_is_flagged(): void
    {
        $this->healthy();
        config(['backup.max_age_days' => 7]);
        Backup::query()->update(['created_at' => now()->subDays(30)]);

        $attention = collect($this->service()->cardSummary()['attention']);
        $stale = $attention->firstWhere('label', 'Last backup');

        $this->assertNotNull($stale);
        $this->assertSame(HealthItem::WARN, $stale['status']);
    }

    public function test_backups_on_the_data_disk_are_flagged(): void
    {
        $this->healthy();
        config(['backup.disk' => 'public', 'filemanager.disk' => 'public']);

        $attention = collect($this->service()->cardSummary()['attention']);
        $dest = $attention->firstWhere('label', 'Backup destination');

        $this->assertNotNull($dest);
        $this->assertSame(HealthItem::WARN, $dest['status']);
    }
}
