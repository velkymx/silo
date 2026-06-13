<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreateDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_document_page_renders_for_valid_type(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('files.new', 'xlsx'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Files/Editor')
                ->where('create.type', 'xlsx')
                ->where('file', null));
    }

    public function test_new_document_page_rejects_unknown_type(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('files.new', 'exe'))->assertNotFound();
    }

    public function test_stores_a_new_spreadsheet_from_the_editor_blob(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $blob = UploadedFile::fake()->createWithContent('Untitled.xlsx', 'XLSXBYTES');

        $this->actingAs($user)->post(route('files.document'), [
            'name' => 'Budget', 'type' => 'xlsx', 'parent_id' => null, 'file' => $blob,
        ])->assertRedirect();

        $file = File::where('owner_id', $user->id)->where('name', 'Budget.xlsx')->firstOrFail();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $file->mime);
        $this->assertSame('XLSXBYTES', Storage::disk('public')->get($file->path));
    }

    public function test_rejects_invalid_document_type(): void
    {
        $user = User::factory()->create();
        $blob = UploadedFile::fake()->createWithContent('x.exe', 'x');
        $this->actingAs($user)->post(route('files.document'), [
            'name' => 'x', 'type' => 'exe', 'file' => $blob,
        ])->assertSessionHasErrors('type');
    }
}
