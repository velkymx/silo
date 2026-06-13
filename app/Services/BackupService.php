<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupService
{
    /** Disk the archives live on (kept private — never the public web root). */
    public const DISK = 'local';

    public const DIR = 'backups';

    /**
     * Build a compressed archive of the database + all stored file blobs and
     * record it. Returns the persisted Backup row (status ready or failed).
     */
    public function create(?int $userId = null): Backup
    {
        $filename = 'backup-'.now()->format('Ymd-His').'.zip';
        $backup = Backup::create([
            'disk' => self::DISK,
            'filename' => $filename,
            'status' => Backup::STATUS_PENDING,
            'created_by' => $userId,
        ]);

        $absDir = Storage::disk(self::DISK)->path(self::DIR);
        if (! is_dir($absDir)) {
            mkdir($absDir, 0775, true);
        }
        $absPath = $absDir.DIRECTORY_SEPARATOR.$filename;

        try {
            $compression = $this->buildArchive($absPath);

            $backup->update([
                'path' => self::DIR.'/'.$filename,
                'size' => is_file($absPath) ? filesize($absPath) : 0,
                'status' => Backup::STATUS_READY,
                'compression' => $compression,
                'note' => 'Database + file blobs.',
            ]);
        } catch (\Throwable $e) {
            @unlink($absPath);
            $backup->update([
                'status' => Backup::STATUS_FAILED,
                'note' => $e->getMessage(),
            ]);
        }

        $this->prune();

        return $backup->refresh();
    }

    /**
     * Write the zip and return the compression method actually used
     * ("bzip2" = ultra, "deflate" = standard fallback).
     */
    private function buildArchive(string $absPath): string
    {
        $zip = new ZipArchive();
        if ($zip->open($absPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the backup archive.');
        }

        // Prefer bzip2 ("ultra"); fall back to deflate if libbz2 is unavailable.
        $method = ZipArchive::CM_BZIP2;
        $label = 'bzip2';
        $probe = $zip->addFromString('.probe', 'x');
        if (! $probe || ! @$zip->setCompressionName('.probe', ZipArchive::CM_BZIP2)) {
            $method = ZipArchive::CM_DEFLATE;
            $label = 'deflate';
        }
        $zip->deleteName('.probe');

        $manifest = [
            'created_at' => now()->toIso8601String(),
            'app' => config('app.name'),
            'db_driver' => config('database.default'),
            'compression' => $label,
            'disks' => [],
        ];

        // 1) Database dump.
        $dumpRel = $this->dumpDatabase();
        $zip->addFile($dumpRel['abs'], 'database/'.$dumpRel['name']);
        $zip->setCompressionName('database/'.$dumpRel['name'], $method);

        // 2) File blobs from the filemanager disk + the thumbnail disk.
        $disks = array_unique([config('filemanager.disk'), ThumbnailGenerator::disk()]);
        foreach ($disks as $diskName) {
            $disk = Storage::disk($diskName);
            $count = 0;
            foreach ($disk->allFiles() as $rel) {
                $entry = "blobs/{$diskName}/{$rel}";
                $stream = $disk->readStream($rel);
                if ($stream === null) {
                    continue;
                }
                $zip->addFromString($entry, stream_get_contents($stream));
                fclose($stream);
                $zip->setCompressionName($entry, $method);
                $count++;
            }
            $manifest['disks'][$diskName] = $count;
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $zip->close();

        if (isset($dumpRel['cleanup']) && $dumpRel['cleanup']) {
            @unlink($dumpRel['abs']);
        }

        return $label;
    }

    /**
     * Produce a database dump on local disk.
     *
     * @return array{abs: string, name: string, cleanup: bool}
     */
    private function dumpDatabase(): array
    {
        $driver = config('database.default');
        $config = config("database.connections.{$driver}");

        // SQLite: the database file IS the dump — copy it verbatim (perfect fidelity).
        if (($config['driver'] ?? null) === 'sqlite') {
            $path = $config['database'];
            if ($path === ':memory:' || ! is_file($path)) {
                // In-memory (tests): emit a lightweight JSON snapshot instead.
                return $this->jsonSnapshot();
            }

            return ['abs' => $path, 'name' => 'database.sqlite', 'cleanup' => false];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'dbdump_');

        if (($config['driver'] ?? null) === 'mysql') {
            $this->run([
                'mysqldump',
                '--host='.($config['host'] ?? '127.0.0.1'),
                '--port='.($config['port'] ?? 3306),
                '--user='.($config['username'] ?? 'root'),
                '--password='.($config['password'] ?? ''),
                $config['database'],
            ], $tmp);

            return ['abs' => $tmp, 'name' => 'database.sql', 'cleanup' => true];
        }

        if (($config['driver'] ?? null) === 'pgsql') {
            $this->run([
                'pg_dump',
                '--host='.($config['host'] ?? '127.0.0.1'),
                '--port='.($config['port'] ?? 5432),
                '--username='.($config['username'] ?? 'postgres'),
                '--dbname='.$config['database'],
            ], $tmp, ['PGPASSWORD' => $config['password'] ?? '']);

            return ['abs' => $tmp, 'name' => 'database.sql', 'cleanup' => true];
        }

        @unlink($tmp);

        return $this->jsonSnapshot();
    }

    /** Portable fallback: dump every table to JSON (used for sqlite :memory: / unknown drivers). */
    private function jsonSnapshot(): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'dbjson_');
        $tables = collect(DB::select('SELECT name FROM sqlite_master WHERE type = "table"'))
            ->pluck('name')
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'));

        $data = [];
        foreach ($tables as $table) {
            $data[$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
        }
        file_put_contents($tmp, json_encode($data));

        return ['abs' => $tmp, 'name' => 'database.json', 'cleanup' => true];
    }

    private function run(array $command, string $outFile, array $env = []): void
    {
        $process = new Process($command, env: $env, timeout: 600);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('Database dump failed: '.trim($process->getErrorOutput()));
        }
        file_put_contents($outFile, $process->getOutput());
    }

    /** Delete oldest backups beyond the configured retention count. */
    public function prune(): void
    {
        $keep = (int) (Setting::get('backup.retention', 7));
        if ($keep <= 0) {
            return;
        }

        Backup::where('status', Backup::STATUS_READY)
            ->orderByDesc('id')
            ->skip($keep)
            ->take(PHP_INT_MAX)
            ->get()
            ->each(fn (Backup $b) => $this->delete($b));
    }

    public function delete(Backup $backup): void
    {
        if ($backup->path) {
            Storage::disk($backup->disk)->delete($backup->path);
        }
        $backup->delete();
    }
}
