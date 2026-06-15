<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

#[Signature('files:import
    {--disk= : Filesystem disk pointing at the server folder to import}
    {--owner= : Owner user id or email}
    {--path= : Subpath within the disk to import (default: the disk root)}
    {--into= : Destination folder id in the owner\'s tree (default: root)}
    {--name= : Name for the imported top-level folder (default: the source folder name)}
    {--no-process : Skip metadata/thumbnail jobs}')]
#[Description('Index an existing server folder (a disk or symlink) into a user\'s file tree, in place')]
class ImportFolder extends Command
{
    private bool $process = true;

    public function handle(): int
    {
        $diskName = $this->option('disk');
        if (! $diskName || ! array_key_exists($diskName, config('filesystems.disks', []))) {
            $this->error('Provide a valid --disk configured in config/filesystems.php.');

            return self::FAILURE;
        }

        $owner = $this->resolveOwner();
        if (! $owner) {
            $this->error('Provide a valid --owner (user id or email).');

            return self::FAILURE;
        }

        $into = $this->option('into')
            ? File::folders()->where('owner_id', $owner->id)->find($this->option('into'))
            : null;
        if ($this->option('into') && ! $into) {
            $this->error('The --into folder was not found for this owner.');

            return self::FAILURE;
        }

        $disk = Storage::disk($diskName);
        $base = trim((string) $this->option('path'), '/');
        $this->process = ! $this->option('no-process');

        // Create the wrapping top-level folder.
        $topName = $this->option('name') ?: (basename($base) ?: $diskName);
        $top = File::firstOrCreate(
            ['owner_id' => $owner->id, 'parent_id' => $into?->id, 'name' => $topName],
            ['is_dir' => true, 'disk' => $diskName, 'path' => $base ?: $topName, 'referenced' => true],
        );

        $count = $this->importInto($disk, $diskName, $base, $owner->id, $top->id);

        $this->info("Imported {$count} file(s) from [{$diskName}:{$base}] into \"{$topName}\".");

        return self::SUCCESS;
    }

    private function importInto(Filesystem $disk, string $diskName, string $path, int $ownerId, int $parentId): int
    {
        $count = 0;

        foreach ($disk->directories($path) as $dir) {
            $folder = File::firstOrCreate(
                ['owner_id' => $ownerId, 'parent_id' => $parentId, 'name' => basename($dir)],
                ['is_dir' => true, 'disk' => $diskName, 'path' => $dir, 'referenced' => true],
            );

            $count += $this->importInto($disk, $diskName, $dir, $ownerId, $folder->id);
        }

        foreach ($disk->files($path) as $filePath) {
            $file = File::firstOrCreate(
                ['owner_id' => $ownerId, 'parent_id' => $parentId, 'name' => basename($filePath)],
                [
                    'is_dir' => false,
                    'disk' => $diskName,
                    'path' => $filePath,
                    'mime' => $disk->mimeType($filePath) ?: null,
                    'size' => $disk->size($filePath),
                    'referenced' => true,
                    'status' => File::STATUS_PENDING,
                ],
            );

            if ($file->wasRecentlyCreated && $this->process) {
                ProcessUploadedFile::dispatch($file->id);
            }

            $count++;
        }

        return $count;
    }

    private function resolveOwner(): ?User
    {
        $owner = (string) $this->option('owner');
        if ($owner === '') {
            return null;
        }

        return is_numeric($owner)
            ? User::find($owner)
            : User::where('email', $owner)->first();
    }
}
