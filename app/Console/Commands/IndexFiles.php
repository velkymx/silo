<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

#[Signature('files:index {--disk= : The filesystem disk to index (defaults to filemanager.disk)}')]
#[Description('Index existing disk contents into the files table')]
class IndexFiles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $diskName = $this->option('disk') ?: config('filemanager.disk');
        $disk = Storage::disk($diskName);

        $count = 0;

        foreach (User::all() as $user) {
            $root = "uploads/{$user->id}";

            if (! $disk->exists($root)) {
                continue;
            }

            $count += $this->indexDirectory($disk, $diskName, $root, $user->id, null);
        }

        $this->info("Indexed {$count} item(s).");

        return self::SUCCESS;
    }

    /**
     * Recursively index a directory, returning the number of rows touched.
     */
    protected function indexDirectory(Filesystem $disk, string $diskName, string $path, int $ownerId, ?int $parentId): int
    {
        $count = 0;

        foreach ($disk->directories($path) as $dir) {
            $folder = File::firstOrCreate(
                ['parent_id' => $parentId, 'name' => basename($dir), 'owner_id' => $ownerId],
                ['path' => $dir, 'disk' => $diskName, 'is_dir' => true],
            );

            $count++;
            $count += $this->indexDirectory($disk, $diskName, $dir, $ownerId, $folder->id);
        }

        foreach ($disk->files($path) as $file) {
            File::firstOrCreate(
                ['parent_id' => $parentId, 'name' => basename($file), 'owner_id' => $ownerId],
                [
                    'path' => $file,
                    'disk' => $diskName,
                    'is_dir' => false,
                    'mime' => $disk->mimeType($file) ?: null,
                    'size' => $disk->size($file),
                    'hash' => hash('sha256', $disk->get($file)),
                ],
            );

            $count++;
        }

        return $count;
    }
}
