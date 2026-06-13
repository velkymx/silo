<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

class ThumbnailGenerator
{
    /** Longest edge of a generated thumbnail, in pixels. */
    public const MAX_EDGE = 320;

    /**
     * Generate a JPEG thumbnail for an image file and return its stored path,
     * or null when the file is not a previewable image.
     */
    public function generate(File $file): ?string
    {
        if ($file->is_dir || ! str_starts_with((string) $file->mime, 'image/')) {
            return null;
        }

        $source = Storage::disk($file->disk);

        if (! $source->exists($file->path)) {
            return null;
        }

        try {
            $image = (new ImageManager(Driver::class))->decodeBinary($source->get($file->path));
            $image->scaleDown(self::MAX_EDGE, self::MAX_EDGE);
            $encoded = (string) $image->encode(new JpegEncoder(quality: 70));
        } catch (Throwable) {
            return null;
        }

        // Thumbnails always live on the app's writable disk, so source files on
        // read-only/imported disks still get previews. Served via files.thumbnail.
        $thumbs = Storage::disk(self::disk());
        $path = "thumbnails/{$file->owner_id}/".Str::random(40).'.jpg';
        $thumbs->put($path, $encoded);

        // Remove a previous thumbnail when regenerating.
        if ($file->thumbnail_path && $file->thumbnail_path !== $path) {
            $thumbs->delete($file->thumbnail_path);
        }

        return $path;
    }

    /** The disk thumbnails are written to and served from. */
    public static function disk(): string
    {
        return config('filemanager.disk');
    }
}
