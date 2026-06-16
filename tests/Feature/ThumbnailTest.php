<?php

namespace Tests\Feature;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\User;
use App\Services\MetadataExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ThumbnailTest extends TestCase
{
    use RefreshDatabase;

    private function png(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    public function test_job_generates_a_thumbnail_for_an_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = 'uploads/'.$user->id.'/big.png';
        Storage::disk('public')->put($path, $this->png(800, 600));

        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'big.png',
            'path' => $path,
            'mime' => 'image/png',
            'status' => File::STATUS_PENDING,
        ]);

        ProcessUploadedFile::dispatchSync($file->id);

        $file->refresh();
        $this->assertNotNull($file->thumbnail_path);
        Storage::disk('public')->assertExists($file->thumbnail_path);

        $info = getimagesizefromstring(Storage::disk('public')->get($file->thumbnail_path));
        $this->assertLessThanOrEqual(320, $info[0]);
        $this->assertLessThanOrEqual(320, $info[1]);
    }

    public function test_owner_can_fetch_thumbnail_but_others_cannot(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $thumb = 'thumbnails/'.$owner->id.'/t.jpg';
        Storage::disk('public')->put($thumb, $this->png(32, 32));
        $file = File::factory()->for($owner, 'owner')->create([
            'mime' => 'image/png',
            'thumbnail_path' => $thumb,
        ]);

        $this->actingAs($owner)->get(route('files.thumbnail', $file))->assertOk();
        $this->actingAs($other)->get(route('files.thumbnail', $file))->assertForbidden();
    }

    public function test_thumbnail_route_404_when_absent(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['thumbnail_path' => null]);

        $this->actingAs($user)->get(route('files.thumbnail', $file))->assertNotFound();
    }

    public function test_pdf_gets_a_first_page_thumbnail_when_supported(): void
    {
        if (! \App\Services\ThumbnailGenerator::supportsPdf()) {
            $this->markTestSkipped('Imagick + Ghostscript not available for PDF rasterization.');
        }

        Storage::fake('public');
        $user = User::factory()->create();
        $path = 'uploads/'.$user->id.'/doc.pdf';
        Storage::disk('public')->put($path, $this->minimalPdf());
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'doc.pdf', 'path' => $path, 'mime' => 'application/pdf', 'status' => File::STATUS_PENDING,
        ]);

        ProcessUploadedFile::dispatchSync($file->id);

        $this->assertNotNull($file->fresh()->thumbnail_path);
        Storage::disk('public')->assertExists($file->fresh()->thumbnail_path);
    }

    private function minimalPdf(): string
    {
        return "%PDF-1.1\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF";
    }

    public function test_text_file_gets_preview_snippet(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $path = 'uploads/'.$user->id.'/note.txt';
        Storage::disk('public')->put($path, 'The quick brown fox.');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'note.txt',
            'path' => $path,
            'mime' => 'text/plain',
        ]);

        $meta = (new MetadataExtractor)->extract($file);

        $this->assertSame('The quick brown fox.', $meta['preview']);
    }
}
