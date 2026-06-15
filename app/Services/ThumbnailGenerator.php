<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use Throwable;

class ThumbnailGenerator
{
    /** Longest edge of a generated thumbnail, in pixels. */
    public const MAX_EDGE = 320;

    /**
     * Generate a JPEG thumbnail for a previewable file and return its stored
     * path, or null when no thumbnail can be produced.
     */
    public function generate(File $file): ?string
    {
        if ($file->is_dir) {
            return null;
        }

        $source = Storage::disk($file->disk);
        if (! $source->exists($file->path)) {
            return null;
        }

        $mime = (string) $file->mime;
        $encoded = match (true) {
            str_starts_with($mime, 'image/') => $this->fromImage($source->get($file->path)),
            $mime === 'application/pdf' || str_ends_with(strtolower($file->name), '.pdf')
                => $this->fromPdf($source->get($file->path)),
            default => null,
        };

        if ($encoded === null) {
            return null;
        }

        // Thumbnails always live on the app's writable disk, so source files on
        // read-only/imported disks still get previews. Served via files.thumbnail.
        $thumbs = Storage::disk(self::disk());
        $path = "thumbnails/{$file->owner_id}/".Str::random(40).'.jpg';
        $thumbs->put($path, $encoded);

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

    /** Whether first-page PDF thumbnails can be produced (needs Imagick + Ghostscript). */
    public static function supportsPdf(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        if (! extension_loaded('imagick') || ! in_array('PDF', Imagick::queryFormats('PDF') ?: [], true)) {
            return $cached = false;
        }

        // Imagick can REGISTER the PDF format yet still fail to rasterize it when
        // the Ghostscript delegate is missing or ImageMagick's policy.xml blocks
        // PDF. Probe a minimal PDF once so we report real capability.
        try {
            $probe = new Imagick();
            $probe->setResolution(12, 12);
            $probe->readImageBlob(self::MINIMAL_PDF);
            $probe->clear();
            $probe->destroy();

            return $cached = true;
        } catch (Throwable) {
            return $cached = false;
        }
    }

    /** A minimal one-page PDF used only to probe PDF rasterization support. */
    private const MINIMAL_PDF = "%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 72 72]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

    /** Downscaled JPEG bytes for a raster image, or null on failure. */
    protected function fromImage(string $contents): ?string
    {
        try {
            $image = (new ImageManager(Driver::class))->decodeBinary($contents);
            $image->scaleDown(self::MAX_EDGE, self::MAX_EDGE);

            return (string) $image->encode(new JpegEncoder(quality: 70));
        } catch (Throwable) {
            return null;
        }
    }

    /** First-page JPEG bytes for a PDF via Imagick/Ghostscript, or null. */
    protected function fromPdf(string $contents): ?string
    {
        if (! self::supportsPdf()) {
            return null;
        }

        try {
            $pdf = new Imagick();
            $pdf->setResolution(120, 120);
            $pdf->readImageBlob($contents);
            $pdf->setIteratorIndex(0);

            $page = $pdf->getImage();
            $page->setImageBackgroundColor('white');
            $page = $page->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $page->setImageFormat('jpeg');
            $page->setImageCompressionQuality(70);
            $page->scaleImage(self::MAX_EDGE, self::MAX_EDGE, true);

            return $page->getImageBlob();
        } catch (Throwable) {
            return null;
        }
    }
}
