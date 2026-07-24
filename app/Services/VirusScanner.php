<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class VirusScanner
{
    public const CLEAN = 'clean';

    public const INFECTED = 'infected';

    public const ERROR = 'error';

    public function enabled(): bool
    {
        return (bool) config('filemanager.av.enabled', false);
    }

    /**
     * Scan a stored file. Returns one of CLEAN / INFECTED / ERROR.
     * When scanning is disabled this is a no-op that reports CLEAN.
     */
    public function scan(File $file): string
    {
        if (! $this->enabled()) {
            return self::CLEAN;
        }

        [$path, $cleanup] = $this->localPath($file);
        if ($path === null) {
            return self::ERROR;
        }

        try {
            $command = trim((string) config('filemanager.av.command'));
            $process = Process::fromShellCommandline(
                $command.' '.escapeshellarg($path),
                timeout: (float) config('filemanager.av.timeout', 120),
            );
            $process->run();

            return match ($process->getExitCode()) {
                0 => self::CLEAN,
                1 => self::INFECTED,
                default => self::ERROR,
            };
        } catch (\Throwable $e) {
            return self::ERROR;
        } finally {
            if ($cleanup && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Resolve a local filesystem path the scanner can read. Local disks expose
     * one directly; remote disks are streamed to a temp file (cleaned up after).
     *
     * @return array{0: ?string, 1: bool} [path, needsCleanup]
     */
    private function localPath(File $file): array
    {
        $disk = Storage::disk($file->disk);

        try {
            $direct = $disk->path($file->path);
            if (is_file($direct)) {
                return [$direct, false];
            }
        } catch (\Throwable $e) {
            // Disk has no local path (e.g. s3) — fall through to temp copy.
        }

        $stream = $disk->readStream($file->path);
        if ($stream === null) {
            return [null, false];
        }

        $tmp = $this->writeTempFromStream($stream);

        return [$tmp, true];
    }

    /** Write a PHP stream resource to a temp file and return its path. */
    public function writeTempFromStream(mixed $stream): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'avscan_');
        $dest = fopen($tmp, 'wb');
        stream_copy_to_stream($stream, $dest);
        fclose($dest);
        fclose($stream);

        return $tmp;
    }
}
