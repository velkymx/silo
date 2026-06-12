<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use App\Services\MetadataExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetadataExtractorTest extends TestCase
{
    use RefreshDatabase;

    private function storedFile(User $user, string $name, string $mime, string $contents): File
    {
        $path = 'uploads/'.$user->id.'/'.$name;
        Storage::disk('public')->put($path, $contents);

        return File::factory()->for($user, 'owner')->create([
            'name' => $name,
            'path' => $path,
            'mime' => $mime,
        ]);
    }

    public function test_extracts_image_dimensions(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = $this->storedFile($user, 'p.png', 'image/png', $this->png(24, 12));

        $meta = (new MetadataExtractor)->extract($file);

        $this->assertSame(24, $meta['width']);
        $this->assertSame(12, $meta['height']);
    }

    public function test_extracts_audio_properties_from_wav(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = $this->storedFile($user, 'tone.wav', 'audio/x-wav', $this->wav());

        $meta = (new MetadataExtractor)->extract($file);

        $this->assertSame(8000, $meta['sample_rate']);
        $this->assertSame(1, $meta['channels']);
        $this->assertArrayHasKey('duration', $meta);
    }

    public function test_non_media_returns_empty(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = $this->storedFile($user, 'data.bin', 'application/octet-stream', 'hello world');

        $this->assertSame([], (new MetadataExtractor)->extract($file));
    }

    // Build a PNG of the given dimensions via GD.
    private function png(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    // Build a minimal valid mono 8-bit 8000 Hz PCM WAV (~0.1s).
    private function wav(): string
    {
        $sampleRate = 8000;
        $samples = 800;
        $data = str_repeat(chr(128), $samples); // silence
        $dataSize = strlen($data);

        return 'RIFF'
            .pack('V', 36 + $dataSize)
            .'WAVE'
            .'fmt '
            .pack('V', 16)        // subchunk1 size
            .pack('v', 1)         // PCM
            .pack('v', 1)         // mono
            .pack('V', $sampleRate)
            .pack('V', $sampleRate) // byte rate (sr * channels * bytesPerSample)
            .pack('v', 1)         // block align
            .pack('v', 8)         // bits per sample
            .'data'
            .pack('V', $dataSize)
            .$data;
    }
}
