<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadCrossOwnerCollisionTest extends TestCase
{
    use RefreshDatabase;

    // C3: uploads only ever target the uploader's OWN folders (resolveFolder
    // owner-scopes), so a collaborator cannot create a shadow row in someone
    // else's folder — the request 404s and no duplicate is created.
    public function test_collaborator_cannot_upload_into_owners_folder(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $collaborator = User::factory()->create();

        $folder = File::factory()->for($owner, 'owner')->folder()->create(['name' => 'Team', 'parent_id' => null]);
        File::factory()->for($owner, 'owner')->create(['name' => 'shared.txt', 'parent_id' => $folder->id]);

        Permission::create([
            'file_id' => $folder->id,
            'subject_type' => 'user',
            'subject_id' => $collaborator->id,
            'ability' => Permission::ABILITY_WRITE,
        ]);

        $this->actingAs($collaborator)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->create('shared.txt', 8)],
            'parent_id' => $folder->id,
        ])->assertNotFound();

        // Still exactly one shared.txt in the folder (the owner's).
        $this->assertSame(1, File::where('name', 'shared.txt')->where('parent_id', $folder->id)->count());
    }
}
