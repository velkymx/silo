<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CspScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_pages_have_no_unsafe_eval(): void
    {
        $user = User::factory()->create();
        $csp = $this->actingAs($user)->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
    }

    public function test_document_editor_page_allows_unsafe_eval(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/s.xlsx', 'x');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 's.xlsx', 'path' => $path, 'disk' => 'public', 'mime' => 'application/vnd.ms-excel',
        ]);

        $csp = $this->actingAs($user)->get("/files/{$file->id}/edit")->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("'unsafe-eval'", $csp);
    }
}
