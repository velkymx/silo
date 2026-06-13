<?php

namespace App\Services;

use App\Models\Backup;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
            $this->assertFreeSpace($absDir);
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

        // 2) File blobs from the filemanager disk + the thumbnail disk. Each
        //    entry is streamed via addFile (from a local path, or a temp copy
        //    for remote disks) so blobs are never loaded whole into memory.
        $tempCopies = [];
        $disks = array_unique([config('filemanager.disk'), ThumbnailGenerator::disk()]);
        foreach ($disks as $diskName) {
            $disk = Storage::disk($diskName);
            $count = 0;
            foreach ($disk->allFiles() as $rel) {
                $entry = "blobs/{$diskName}/{$rel}";

                $local = $this->localPathFor($disk, $rel);
                if ($local['path'] === null) {
                    continue;
                }
                $zip->addFile($local['path'], $entry);
                $zip->setCompressionName($entry, $method);
                if ($local['temp']) {
                    $tempCopies[] = $local['path'];
                }
                $count++;
            }
            $manifest['disks'][$diskName] = $count;
        }

        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
        $zip->close(); // streams everything to disk here

        foreach ($tempCopies as $t) {
            @unlink($t);
        }
        if (isset($dumpRel['cleanup']) && $dumpRel['cleanup']) {
            @unlink($dumpRel['abs']);
        }

        return $label;
    }

    /**
     * Resolve a local filesystem path the zip can stream from. Local disks
     * expose one directly; remote disks are streamed to a temp file.
     *
     * @return array{path: ?string, temp: bool}
     */
    private function localPathFor($disk, string $rel): array
    {
        try {
            $direct = $disk->path($rel);
            if (is_file($direct)) {
                return ['path' => $direct, 'temp' => false];
            }
        } catch (\Throwable $e) {
            // No local path (e.g. s3) — fall through to a temp copy.
        }

        $stream = $disk->readStream($rel);
        if ($stream === null) {
            return ['path' => null, 'temp' => false];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'bkblob_');
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($stream, $out); // streamed, constant memory
        fclose($out);
        fclose($stream);

        return ['path' => $tmp, 'temp' => true];
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

    /** Abort early if the target volume clearly can't hold the data. */
    private function assertFreeSpace(string $dir): void
    {
        $needed = 0;
        foreach (array_unique([config('filemanager.disk'), ThumbnailGenerator::disk()]) as $diskName) {
            $disk = Storage::disk($diskName);
            foreach ($disk->allFiles() as $rel) {
                try {
                    $needed += $disk->size($rel);
                } catch (\Throwable $e) {
                    // ignore unreadable entry
                }
            }
        }

        $free = @disk_free_space($dir);
        // Compression shrinks the archive, so the raw total is a safe headroom.
        if ($free !== false && $needed > 0 && $free < $needed) {
            throw new \RuntimeException(
                'Insufficient disk space for backup: need ~'.$needed.' bytes, '.$free.' free.'
            );
        }
    }

    /**
     * Restore a backup: replace the database and file blobs from the archive.
     * DESTRUCTIVE — overwrites current data. Returns a short summary.
     */
    public function restore(Backup $backup): array
    {
        $archive = Storage::disk($backup->disk)->path($backup->path);
        if (! $backup->path || ! is_file($archive)) {
            throw new \RuntimeException('Backup archive is missing.');
        }

        $work = sys_get_temp_dir().'/restore_'.Str::random(12);
        mkdir($work, 0775, true);

        $zip = new ZipArchive();
        if ($zip->open($archive) !== true) {
            throw new \RuntimeException('Could not open the backup archive.');
        }
        $zip->extractTo($work);
        $zip->close();

        try {
            $this->restoreDatabase($work);
            $disks = $this->restoreBlobs($work);

            return ['disks' => $disks];
        } finally {
            $this->rmrf($work);
        }
    }

    private function restoreDatabase(string $work): void
    {
        $dir = $work.'/database';
        $driver = config('database.default');
        $config = config("database.connections.{$driver}");

        if (is_file($dir.'/database.sqlite') && ($config['driver'] ?? null) === 'sqlite'
            && ($config['database'] ?? null) !== ':memory:' && $config['database']) {
            DB::disconnect();
            copy($dir.'/database.sqlite', $config['database']);

            return;
        }

        if (is_file($dir.'/database.sql')) {
            $sql = file_get_contents($dir.'/database.sql');
            DB::unprepared($sql);

            return;
        }

        if (is_file($dir.'/database.json')) {
            $data = json_decode(file_get_contents($dir.'/database.json'), true) ?: [];
            $this->restoreJsonSnapshot($data);

            return;
        }

        throw new \RuntimeException('No database dump found in the archive.');
    }

    /** Truncate + reinsert every table from a JSON snapshot (FK checks off). */
    private function restoreJsonSnapshot(array $data): void
    {
        Schema::disableForeignKeyConstraints();
        try {
            foreach ($data as $table => $rows) {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                DB::table($table)->truncate();
                foreach (array_chunk($rows, 500) as $chunk) {
                    if ($chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /** Restore blob files into their disks. Returns per-disk counts. */
    private function restoreBlobs(string $work): array
    {
        $base = $work.'/blobs';
        $counts = [];
        if (! is_dir($base)) {
            return $counts;
        }

        foreach (scandir($base) as $diskName) {
            if ($diskName === '.' || $diskName === '..' || ! is_dir($base.'/'.$diskName)) {
                continue;
            }
            $disk = Storage::disk($diskName);
            $root = $base.'/'.$diskName;
            $count = 0;
            $iter = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iter as $fileInfo) {
                if (! $fileInfo->isFile()) {
                    continue;
                }
                $rel = ltrim(str_replace($root, '', $fileInfo->getPathname()), '/\\');
                $stream = fopen($fileInfo->getPathname(), 'rb');
                $disk->writeStream($rel, $stream);
                fclose($stream);
                $count++;
            }
            $counts[$diskName] = $count;
        }

        return $counts;
    }

    private function rmrf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
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
