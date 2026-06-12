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
            str_starts_with($mime, 'image/') => $this->image($disk->get($file->path)),
            str_starts_with($mime, 'audio/'),
            str_starts_with($mime, 'video/') => $this->media($disk, $file->path),
            str_starts_with($mime, 'text/') => $this->text($disk->get($file->path)),
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
    protected function image(string $contents): array
    {
        $meta = [];

        if (($info = @getimagesizefromstring($contents)) !== false) {
            $meta['width'] = $info[0];
            $meta['height'] = $info[1];
        }

        if (extension_loaded('exif')) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
            if (is_array($exif)) {
                $meta = array_merge($meta, array_filter([
                    'camera_make' => $exif['Make'] ?? null,
                    'camera_model' => $exif['Model'] ?? null,
                    'taken_at' => $exif['DateTimeOriginal'] ?? null,
                    'orientation' => $exif['Orientation'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''));
            }
        }

        return $meta;
    }

    /**
     * Audio/video tags and playback info via getID3.
     *
     * @param  \Illuminate\Contracts\Filesystem\Filesystem  $disk
     * @return array<string, mixed>
     */
    protected function media($disk, string $path): array
    {
        // getID3 needs a real local path; stage remote/disk contents in a temp file.
        $tmp = tempnam(sys_get_temp_dir(), 'meta');
        file_put_contents($tmp, $disk->get($path));

        try {
            $data = (new getID3)->analyze($tmp);
        } finally {
            @unlink($tmp);
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
