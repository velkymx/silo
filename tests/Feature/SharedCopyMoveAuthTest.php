<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedCopyMoveAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_grantee_cannot_copy_into_owners_folder(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create();

        Storage::disk('public')->put('uploads/'.$owner->id.'/a.txt', 'x');
        $file = File::factory()->for($owner, 'owner')->create([
            'name' => 'a.txt', 'path' => 'uploads/'.$owner->id.'/a.txt', 'disk' => 'public',
        ]);
        $ownerFolder = File::factory()->for($owner, 'owner')->folder()->create();

        Permission::factory()->forUser($grantee)->ability(Permission::ABILITY_READ)->create([
            'file_id' => $file->id,
        ]);

        // Grantee can view the file, but copying it into the OWNER's folder must fail.
        $this->actingAs($grantee)
            ->post("/files/{$file->id}/copy", ['target_id' => $ownerFolder->id])
            ->assertStatus(404);
    }

    public function test_read_grantee_cannot_move_shared_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();
        $granteeFolder = File::factory()->for($grantee, 'owner')->folder()->create();

        Permission::factory()->forUser($grantee)->ability(Permission::ABILITY_READ)->create([
            'file_id' => $file->id,
        ]);

        // Read-only grantee has no write on the source → move is forbidden.
        $this->actingAs($grantee)
            ->post("/files/{$file->id}/move", ['target_id' => $granteeFolder->id])
            ->assertForbidden();
    }

    public function test_write_grantee_cannot_move_owners_file_into_their_own_folder(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();
        $granteeFolder = File::factory()->for($grantee, 'owner')->folder()->create();

        // Write grant → passes the update policy, but the move must not relocate
        // the file out of the owner's tree (it would vanish from the owner's view).
        Permission::factory()->forUser($grantee)->ability(Permission::ABILITY_WRITE)->create([
            'file_id' => $file->id,
        ]);

        $this->actingAs($grantee)
            ->post("/files/{$file->id}/move", ['target_id' => $granteeFolder->id])
            ->assertSessionHasErrors('target_id');

        $this->assertNull($file->fresh()->parent_id);
    }

    public function test_grantee_can_copy_a_viewable_file_into_their_own_folder(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create();
        Storage::disk('public')->put('uploads/'.$owner->id.'/a.txt', 'x');
        $file = File::factory()->for($owner, 'owner')->create([
            'name' => 'a.txt', 'path' => 'uploads/'.$owner->id.'/a.txt', 'disk' => 'public',
        ]);
        $granteeFolder = File::factory()->for($grantee, 'owner')->folder()->create();

        Permission::factory()->forUser($grantee)->ability(Permission::ABILITY_READ)->create([
            'file_id' => $file->id,
        ]);

        $this->actingAs($grantee)
            ->post("/files/{$file->id}/copy", ['target_id' => $granteeFolder->id])
            ->assertRedirect();

        // The copy belongs to the grantee, in their folder.
        $this->assertDatabaseHas('files', [
            'name' => 'a.txt', 'owner_id' => $grantee->id, 'parent_id' => $granteeFolder->id,
        ]);
    }
}
