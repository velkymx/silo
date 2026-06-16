<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RawServingSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function fileFor(User $user, string $name, string $mime, string $body): File
    {
        Storage::fake('public');
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/'.$name, $body);

        return File::factory()->for($user, 'owner')->create([
            'name' => $name, 'path' => $path, 'disk' => 'public', 'mime' => $mime,
        ]);
    }

    public function test_html_is_served_as_attachment_not_inline(): void
    {
        $user = User::factory()->create();
        $file = $this->fileFor($user, 'evil.html', 'text/html', '<script>alert(1)</script>');

        $res = $this->actingAs($user)->get(route('files.raw', $file));

        $res->assertOk();
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->assertSame('application/octet-stream', $res->headers->get('Content-Type'));
    }

    public function test_svg_is_never_inline(): void
    {
        $user = User::factory()->create();
        $file = $this->fileFor($user, 'logo.svg', 'image/svg+xml', '<svg onload="alert(1)"></svg>');

        $res = $this->actingAs($user)->get(route('files.raw', $file));

        $res->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
    }

    public function test_images_and_pdf_remain_inline(): void
    {
        $user = User::factory()->create();

        foreach (['p.png' => 'image/png', 'd.pdf' => 'application/pdf'] as $name => $mime) {
            $file = $this->fileFor($user, $name, $mime, 'binary');
            $res = $this->actingAs($user)->get(route('files.raw', $file));

            $res->assertOk();
            $res->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('inline', $res->headers->get('Content-Disposition'));
            $this->assertSame($mime, $res->headers->get('Content-Type'));
        }
    }
}
