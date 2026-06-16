<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_folder_from_selection_moves_items_in(): void
    {
        $user = User::factory()->create();
        $a = File::factory()->for($user, 'owner')->create(['name' => 'a.txt', 'parent_id' => null]);
        $b = File::factory()->for($user, 'owner')->create(['name' => 'b.txt', 'parent_id' => null]);

        $this->actingAs($user)->post(route('files.batch.folder'), [
            'name' => 'Bundle', 'ids' => [$a->id, $b->id], 'parent_id' => null,
        ])->assertRedirect();

        $folder = File::where('name', 'Bundle')->where('is_dir', true)->firstOrFail();
        $this->assertSame($folder->id, $a->fresh()->parent_id);
        $this->assertSame($folder->id, $b->fresh()->parent_id);
    }

    public function test_batch_move(): void
    {
        $user = User::factory()->create();
        $dest = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Dest']);
        $a = File::factory()->for($user, 'owner')->create(['name' => 'a.txt']);

        $this->actingAs($user)->post(route('files.batch.move'), ['ids' => [$a->id], 'target_id' => $dest->id])
            ->assertRedirect();
        $this->assertSame($dest->id, $a->fresh()->parent_id);
    }

    public function test_batch_rename_applies_final_names(): void
    {
        $user = User::factory()->create();
        $a = File::factory()->for($user, 'owner')->create(['name' => 'IMG1.jpg']);
        $b = File::factory()->for($user, 'owner')->create(['name' => 'IMG2.jpg']);

        $this->actingAs($user)->post(route('files.batch.rename'), [
            'renames' => [
                ['id' => $a->id, 'name' => 'Trip-01.jpg'],
                ['id' => $b->id, 'name' => 'Trip-02.jpg'],
            ],
        ])->assertRedirect();

        $this->assertSame('Trip-01.jpg', $a->fresh()->name);
        $this->assertSame('Trip-02.jpg', $b->fresh()->name);
    }

    public function test_batch_delete_trashes(): void
    {
        $user = User::factory()->create();
        $a = File::factory()->for($user, 'owner')->create(['name' => 'a.txt']);

        $this->actingAs($user)->post(route('files.batch.delete'), ['ids' => [$a->id]])->assertRedirect();
        $this->assertSoftDeleted('files', ['id' => $a->id]);
    }

    public function test_batch_ignores_other_users_files(): void
    {
        $user = User::factory()->create();
        $mine = File::factory()->for($user, 'owner')->create(['name' => 'mine.txt']);
        $theirs = File::factory()->for(User::factory()->create(), 'owner')->create(['name' => 'theirs.txt']);

        $this->actingAs($user)->post(route('files.batch.delete'), ['ids' => [$mine->id, $theirs->id]])->assertRedirect();
        $this->assertSoftDeleted('files', ['id' => $mine->id]);
        $this->assertDatabaseHas('files', ['id' => $theirs->id, 'deleted_at' => null]);
    }
}
