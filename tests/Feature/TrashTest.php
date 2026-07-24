<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase;

    private function storedFile(User $owner, ?int $parentId = null, string $name = 'a.txt'): File
    {
        Storage::disk('public')->put($path = 'uploads/'.$owner->id.'/'.uniqid(), 'data');

        return File::factory()->for($owner, 'owner')->create([
            'name' => $name, 'path' => $path, 'parent_id' => $parentId,
        ]);
    }

    public function test_deleting_keeps_the_blob_and_lists_in_trash(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = $this->storedFile($user);

        $this->actingAs($user)->delete(route('files.delete', $file))->assertRedirect();

        Storage::disk('public')->assertExists($file->path);
        $this->actingAs($user)->get('/trash')->assertInertia(
            fn (Assert $p) => $p->component('Files/Index')->where('section', 'trash')
                ->has('files', 1)->where('files.0.id', $file->id)
        );
    }

    public function test_trash_lists_only_deletion_roots_not_children(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create();
        $this->storedFile($user, $folder->id);

        $this->actingAs($user)->delete(route('files.delete', $folder))->assertRedirect();

        // The child is trashed too, but only the folder shows as a root.
        $this->actingAs($user)->get('/trash')->assertInertia(
            fn (Assert $p) => $p->component('Files/Index')->where('section', 'trash')
                ->has('folders', 1)->where('folders.0.id', $folder->id)
        );
    }

    public function test_restoring_a_folder_restores_its_subtree(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create();
        $child = $this->storedFile($user, $folder->id);
        $this->actingAs($user)->delete(route('files.delete', $folder));

        $this->actingAs($user)->post(route('trash.restore', $folder))->assertRedirect();

        $this->assertNull($folder->fresh()->deleted_at);
        $this->assertNull($child->fresh()->deleted_at);
    }

    public function test_purging_a_file_removes_row_and_blob(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = $this->storedFile($user);
        FileVersion::factory()->create(['file_id' => $file->id, 'path' => $vp = 'uploads/'.$user->id.'/v1']);
        Storage::disk('public')->put($vp, 'old');
        $this->actingAs($user)->delete(route('files.delete', $file));

        $this->actingAs($user)->delete(route('trash.destroy', $file))->assertRedirect();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        Storage::disk('public')->assertMissing($file->path);
        Storage::disk('public')->assertMissing($vp);
    }

    public function test_purging_a_folder_removes_subtree_rows_and_blobs(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create();
        $child = $this->storedFile($user, $folder->id);
        $this->actingAs($user)->delete(route('files.delete', $folder));

        $this->actingAs($user)->delete(route('trash.destroy', $folder))->assertRedirect();

        $this->assertDatabaseMissing('files', ['id' => $folder->id]);
        $this->assertDatabaseMissing('files', ['id' => $child->id]);
        Storage::disk('public')->assertMissing($child->path);
    }

    public function test_empty_trash_purges_everything(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $a = $this->storedFile($user);
        $b = $this->storedFile($user);
        $this->actingAs($user)->delete(route('files.delete', $a));
        $this->actingAs($user)->delete(route('files.delete', $b));

        $this->actingAs($user)->delete(route('trash.empty'))->assertRedirect();

        $this->assertSame(0, File::onlyTrashed()->count());
    }

    public function test_cannot_restore_another_users_trashed_item(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = $this->storedFile($owner);
        $this->actingAs($owner)->delete(route('files.delete', $file));

        $this->actingAs($other)->post(route('trash.restore', $file))->assertForbidden();
    }

    public function test_purge_command_removes_old_trash_only(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $old = $this->storedFile($user);
        $recent = $this->storedFile($user);
        $this->actingAs($user)->delete(route('files.delete', $old));
        $this->actingAs($user)->delete(route('files.delete', $recent));
        File::onlyTrashed()->whereKey($old->id)->update(['deleted_at' => now()->subDays(40)]);

        $this->artisan('trash:purge', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('files', ['id' => $old->id]);
        $this->assertSame(1, File::onlyTrashed()->count());
    }
}
