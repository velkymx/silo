<?php

namespace App\Console\Commands\Photos;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use Illuminate\Console\Command;

/**
 * Re-run metadata extraction for existing images so the EXIF/IPTC/XMP/GPS
 * photo block appears on photos uploaded before enrichment shipped.
 */
class EnrichPhotoMetadata extends Command
{
    protected $signature = 'photos:enrich {--missing-only : Skip images that already carry a metadata.photo block}';

    protected $description = 'Re-extract metadata (EXIF/IPTC/XMP/GPS) for stored images';

    public function handle(): int
    {
        $query = File::query()->files()->where('mime', 'like', 'image/%');
        if ($this->option('missing-only')) {
            $query->where(fn ($q) => $q->whereNull('metadata')->orWhere('metadata', 'not like', '%"photo"%'));
        }

        $count = 0;
        $query->orderBy('id')->chunkById(200, function ($files) use (&$count) {
            foreach ($files as $file) {
                ProcessUploadedFile::dispatch($file->id);
                $count++;
            }
        });

        $this->info("Queued {$count} image(s) for metadata enrichment.");

        return self::SUCCESS;
    }
}
