<?php

namespace App\Services;

use App\Models\File;
use getID3;
use Illuminate\Support\Facades\Storage;

class MetadataExtractor
{
    /**
     * Extract type-specific metadata for a stored file.
     *
     * @return array<string, mixed>
     */
    public function extract(File $file): array
    {
        $disk = Storage::disk($file->disk);

        if ($file->is_dir || ! $disk->exists($file->path)) {
            return [];
        }

        $mime = (string) $file->mime;

        return match (true) {
            str_starts_with($mime, 'image/') => $this->image($disk, $file->path),
            str_starts_with($mime, 'audio/'),
            str_starts_with($mime, 'video/') => $this->media($disk, $file->path),
            str_starts_with($mime, 'text/') => $this->text($this->readHead($disk, $file->path, 500)),
            default => [],
        };
    }

    /**
     * A short snippet preview for text files.
     *
     * @return array<string, mixed>
     */
    protected function text(string $contents): array
    {
        $snippet = trim(mb_substr($contents, 0, 500));

        return $snippet === '' ? [] : ['preview' => $snippet];
    }

    /**
     * Image dimensions plus EXIF (camera, capture time, orientation) when present.
     *
     * @return array<string, mixed>
     */
    protected function image($disk, string $path): array
    {
        // Read dimensions/EXIF straight from a file handle (constant memory)
        // instead of loading the whole image into a string.
        [$local, $temp] = $this->localPath($disk, $path);
        if ($local === null) {
            return [];
        }

        try {
            $meta = [];
            $photo = [];
            $parser = app(PhotoMetadata::class);

            // getimagesize's $info side-channel also carries the APP13 (IPTC)
            // segment, so one call yields dimensions + descriptive metadata.
            $info13 = [];
            if (($info = @getimagesize($local, $info13)) !== false) {
                $meta['width'] = $info[0];
                $meta['height'] = $info[1];
            }
            if (isset($info13['APP13']) && ($iptc = @iptcparse($info13['APP13'])) !== false && is_array($iptc)) {
                $iptcMeta = $parser->fromIptc($iptc);
                if ($iptcMeta !== []) {
                    $photo['iptc'] = $iptcMeta;
                }
            }

            if (extension_loaded('exif')) {
                $exif = @exif_read_data($local);
                if (is_array($exif)) {
                    $meta = array_merge($meta, array_filter([
                        'camera_make' => $exif['Make'] ?? null,
                        'camera_model' => $exif['Model'] ?? null,
                        'taken_at' => $exif['DateTimeOriginal'] ?? null,
                        'orientation' => $exif['Orientation'] ?? null,
                    ], fn ($v) => $v !== null && $v !== ''));

                    $photo = array_merge($photo, $parser->fromExif($exif));
                }
            }

            // XMP lives as an XML packet near the head of the file.
            $head = (string) @file_get_contents($local, false, null, 0, 256 * 1024);
            $xmp = $parser->fromXmp($head);
            if ($xmp !== []) {
                $photo['xmp'] = $xmp;
            }

            if ($photo !== []) {
                $meta['photo'] = $photo;
            }

            return $meta;
        } finally {
            if ($temp) {
                @unlink($local);
            }
        }
    }

    /**
     * A local filesystem path for a stored file (direct for local disks, a
     * streamed temp copy for remote ones). Returns [path|null, isTemp].
     *
     * @return array{0: ?string, 1: bool}
     */
    private function localPath($disk, string $rel): array
    {
        try {
            $direct = $disk->path($rel);
            if (is_file($direct)) {
                return [$direct, false];
            }
        } catch (\Throwable $e) {
            // remote disk — fall through to a temp copy
        }

        $stream = $disk->readStream($rel);
        if ($stream === null) {
            return [null, false];
        }
        $tmp = tempnam(sys_get_temp_dir(), 'meta_');
        $out = fopen($tmp, 'wb');
        stream_copy_to_stream($stream, $out);
        fclose($out);
        fclose($stream);

        return [$tmp, true];
    }

    /** Read at most $bytes bytes from a file without loading the entire file into memory. */
    private function readHead($disk, string $rel, int $bytes): string
    {
        try {
            $stream = $disk->readStream($rel);
            if ($stream === null) {
                return '';
            }
            $data = (string) fread($stream, $bytes);
            fclose($stream);
            return $data;
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Audio/video tags and playback info via getID3.
     *
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $disk
     * @return array<string, mixed>
     */
    protected function media($disk, string $path): array
    {
        // getID3 needs a real local path; use localPath() to avoid loading into memory.
        [$local, $temp] = $this->localPath($disk, $path);
        if ($local === null) {
            return [];
        }

        try {
            $data = (new getID3)->analyze($local);
        } finally {
            if ($temp) {
                @unlink($local);
            }
        }

        $tags = $data['tags']['id3v2'] ?? $data['tags']['id3v1'] ?? $data['tags']['vorbiscomment'] ?? [];

        return array_filter([
            'title' => $tags['title'][0] ?? null,
            'artist' => $tags['artist'][0] ?? null,
            'album' => $tags['album'][0] ?? null,
            'duration' => isset($data['playtime_seconds']) ? round($data['playtime_seconds'], 1) : null,
            'bitrate' => isset($data['bitrate']) ? (int) round($data['bitrate'] / 1000) : null,
            'sample_rate' => $data['audio']['sample_rate'] ?? null,
            'channels' => $data['audio']['channels'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }
}
