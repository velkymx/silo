<?php

namespace App\Services\Health;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Operator-facing health checks for the admin System Health card. Organized
 * into four categories, each returning HealthItem[]. Every check is local and
 * cheap except the opt-in Silo update check (network, cached 24h).
 *
 * Philosophy mirrors "Needs Attention": management by exception. The card
 * renders only warn/red items plus a short healthy summary — never a wall of
 * green ticks and never a meaningless "health 92%" score.
 */
class HealthService
{
    /** Jobs waiting in the queue before it counts as a backlog. */
    private const QUEUE_BACKLOG = 100;

    /**
     * The card payload: the count and list of attention items, plus three
     * reassuring facts for the healthy state.
     *
     * @return array{attentionCount: int, attention: array<int, array<string, mixed>>, facts: array<int, string>}
     */
    public function cardSummary(): array
    {
        $attention = array_values(array_filter($this->all(), fn (HealthItem $i) => $i->needsAttention()));

        return [
            'attentionCount' => count($attention),
            'attention' => array_map(fn (HealthItem $i) => $i->toArray(), $attention),
            'facts' => $this->facts(),
        ];
    }

    /**
     * Every check across all categories, in render order.
     *
     * @return array<int, HealthItem>
     */
    public function all(): array
    {
        return array_merge(
            $this->runtime(),
            $this->security(),
            $this->features(),
            $this->maintenance(),
        );
    }

    /** @return array<int, HealthItem> */
    public function runtime(): array
    {
        $items = [
            $this->databaseCheck(),
            $this->storageCheck(),
            $this->cacheCheck(),
            new HealthItem('runtime', 'Maintenance mode',
                app()->isDownForMaintenance() ? HealthItem::WARN : HealthItem::OK,
                app()->isDownForMaintenance()
                    ? 'The app is in maintenance mode; users cannot reach it.'
                    : 'Off.'),
            new HealthItem('runtime', 'Silo version', HealthItem::INFO, 'v'.config('silo.version')),
        ];

        if ($update = $this->updateCheck()) {
            $items[] = $update;
        }

        return $items;
    }

    /** @return array<int, HealthItem> */
    public function security(): array
    {
        $debugOn = (bool) config('app.debug');

        return [
            new HealthItem('security', 'Debug mode',
                ! $debugOn ? HealthItem::OK : (app()->environment('production') ? HealthItem::RED : HealthItem::WARN),
                ! $debugOn ? 'Off.'
                    : 'APP_DEBUG is on; stack traces and internals are exposed.'),
            new HealthItem('security', 'HTTPS',
                str_starts_with((string) config('app.url'), 'https://') ? HealthItem::OK : HealthItem::WARN,
                str_starts_with((string) config('app.url'), 'https://')
                    ? 'APP_URL is https.'
                    : 'APP_URL is not https; traffic may be unencrypted.'),
            new HealthItem('security', 'Vault key',
                config('vault.key') ? HealthItem::OK : HealthItem::WARN,
                config('vault.key')
                    ? 'A dedicated VAULT_KEY is configured.'
                    : 'Falling back to APP_KEY; rotating APP_KEY would lock the vault.'),
            new HealthItem('security', 'PHP version', HealthItem::INFO, PHP_VERSION),
        ];
    }

    /** @return array<int, HealthItem> */
    public function features(): array
    {
        $mailer = (string) config('mail.default');

        return [
            new HealthItem('features', 'Antivirus',
                config('filemanager.antivirus.enabled') ? HealthItem::OK : HealthItem::INFO,
                config('filemanager.antivirus.enabled')
                    ? 'Enabled. Uploads are scanned before being served.'
                    : 'Disabled. Uploads are not scanned before being served.'),
            new HealthItem('features', 'Screenshots',
                config('bookmarks.screenshots.enabled') ? HealthItem::OK : HealthItem::INFO,
                config('bookmarks.screenshots.enabled')
                    ? 'Enabled. Bookmarks capture a page screenshot.'
                    : 'Disabled. Bookmark previews will use favicons only.'),
            new HealthItem('features', 'Email',
                in_array($mailer, ['log', 'array'], true) ? HealthItem::INFO : HealthItem::OK,
                in_array($mailer, ['log', 'array'], true)
                    ? "Not delivering. Using the {$mailer} driver."
                    : "Delivering via the {$mailer} driver."),
            new HealthItem('features', 'Object storage',
                config('filesystems.default') === 's3' ? HealthItem::OK : HealthItem::INFO,
                config('filesystems.default') === 's3'
                    ? 'S3 is configured.'
                    : 'Using the local disk.'),
        ];
    }

    /** @return array<int, HealthItem> */
    public function maintenance(): array
    {
        $failed = DB::table('failed_jobs')->count();
        $queued = DB::table('jobs')->count();

        return [
            new HealthItem('maintenance', 'Failed jobs',
                $failed > 0 ? HealthItem::WARN : HealthItem::OK,
                $failed > 0
                    ? "{$failed} failed ".Str::plural('job', $failed).' need review.'
                    : 'None.'),
            new HealthItem('maintenance', 'Queue',
                $queued > self::QUEUE_BACKLOG ? HealthItem::WARN : HealthItem::OK,
                $queued > self::QUEUE_BACKLOG
                    ? "{$queued} jobs are waiting to run."
                    : 'Running.'),
            $this->lastBackupCheck(),
            $this->backupDestinationCheck(),
        ];
    }

    /** Warn when there is no successful backup, or the latest is stale. */
    private function lastBackupCheck(): HealthItem
    {
        $latest = Backup::query()->where('status', Backup::STATUS_READY)->latest('created_at')->first();

        if ($latest === null) {
            return new HealthItem('maintenance', 'Last backup', HealthItem::WARN,
                'No successful backup has been taken yet.');
        }

        $maxAge = (int) config('backup.max_age_days', 7);
        $ageDays = $latest->created_at->diffInDays(now());

        if ($ageDays > $maxAge) {
            return new HealthItem('maintenance', 'Last backup', HealthItem::WARN,
                "The most recent backup is {$ageDays} days old (older than {$maxAge}).");
        }

        return new HealthItem('maintenance', 'Last backup', HealthItem::OK,
            'Taken '.$latest->created_at->diffForHumans().'.');
    }

    /**
     * A backup on the same volume as the data is not a backup. Warn when the
     * backup disk equals the data disk, and recommend offsite while it stays on
     * this server.
     */
    private function backupDestinationCheck(): HealthItem
    {
        $backupDisk = BackupService::disk();
        $dataDisk = config('filemanager.disk', 'local');

        if ($backupDisk === $dataDisk) {
            return new HealthItem('maintenance', 'Backup destination', HealthItem::WARN,
                "Backups share the data disk ('{$backupDisk}'); one disk failure loses both. Set BACKUP_DISK to a separate, offsite disk.");
        }

        $sameRoot = config("filesystems.disks.{$backupDisk}.root") === config("filesystems.disks.{$dataDisk}.root");
        if ($sameRoot) {
            return new HealthItem('maintenance', 'Backup destination', HealthItem::WARN,
                "Backups ('{$backupDisk}') and data ('{$dataDisk}') resolve to the same location; one disk failure loses both.");
        }

        if (config("filesystems.disks.{$backupDisk}.driver") === 'local') {
            return new HealthItem('maintenance', 'Backup destination', HealthItem::INFO,
                "Backups go to the local disk '{$backupDisk}'. For durability, mount it on an offsite volume.");
        }

        return new HealthItem('maintenance', 'Backup destination', HealthItem::OK,
            "Backups go to the offsite disk '{$backupDisk}'.");
    }

    /**
     * Opt-in Silo update check. Returns a warn item when a newer release exists,
     * null when disabled, offline, or already up to date. Cached to avoid
     * phoning home more than once per `cache_hours`.
     */
    public function updateCheck(): ?HealthItem
    {
        if (! config('silo.update_check.enabled')) {
            return null;
        }

        $latest = $this->latestVersion();
        $current = (string) config('silo.version');

        if ($latest === null || ! version_compare($latest, $current, '>')) {
            return null;
        }

        return new HealthItem('runtime', 'Silo update', HealthItem::WARN,
            "An update is available (v{$current} -> v{$latest}).");
    }

    /** The latest published Silo release tag, or null if unavailable. Cached. */
    public function latestVersion(): ?string
    {
        $hours = (int) config('silo.update_check.cache_hours', 24);

        return Cache::remember('silo.latest-version', now()->addHours($hours), function () {
            try {
                $repo = config('silo.update_check.repo');
                $response = Http::timeout(5)->acceptJson()
                    ->get("https://api.github.com/repos/{$repo}/releases/latest");

                if (! $response->ok()) {
                    return null;
                }

                return ltrim((string) $response->json('tag_name'), 'v') ?: null;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    /**
     * Three reassuring facts for the healthy state.
     *
     * @return array<int, string>
     */
    private function facts(): array
    {
        return [
            'Silo v'.config('silo.version'),
            'Database reachable',
            'Queue running',
        ];
    }

    private function databaseCheck(): HealthItem
    {
        try {
            DB::connection()->getPdo();

            return new HealthItem('runtime', 'Database', HealthItem::OK, 'Reachable.');
        } catch (\Throwable) {
            return new HealthItem('runtime', 'Database', HealthItem::RED, 'Not reachable.');
        }
    }

    private function storageCheck(): HealthItem
    {
        try {
            $disk = Storage::disk(config('filemanager.disk'));
            $probe = '.health-'.Str::random(8);
            $disk->put($probe, 'ok');
            $disk->delete($probe);

            return new HealthItem('runtime', 'Storage', HealthItem::OK, 'The storage disk is writable.');
        } catch (\Throwable) {
            return new HealthItem('runtime', 'Storage', HealthItem::RED, 'The storage disk is not writable.');
        }
    }

    private function cacheCheck(): HealthItem
    {
        try {
            $key = 'health.probe.'.Str::random(6);
            Cache::put($key, '1', 5);
            $ok = Cache::get($key) === '1';
            Cache::forget($key);

            return new HealthItem('runtime', 'Cache',
                $ok ? HealthItem::OK : HealthItem::WARN,
                $ok ? 'Working.' : 'Not returning stored values.');
        } catch (\Throwable) {
            return new HealthItem('runtime', 'Cache', HealthItem::RED, 'Not working.');
        }
    }
}
