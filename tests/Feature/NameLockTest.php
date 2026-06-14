<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NameLockTest extends TestCase
{
    use RefreshDatabase;

    // C4: copying the same source into the same folder twice must produce two
    // distinct names (auto-suffixed) without a unique-constraint 500. The
    // create path is serialized by a folder write-lock.
    public function test_repeated_copy_auto_suffixes_without_error(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $dest = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Dest']);
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/a', 'x');
        $src = File::factory()->for($user, 'owner')->create(['name' => 'a.txt', 'path' => $path, 'disk' => 'public']);

        $this->actingAs($user)->post(route('files.copy', $src), ['target_id' => $dest->id])->assertRedirect();
        $this->actingAs($user)->post(route('files.copy', $src), ['target_id' => $dest->id])->assertRedirect();

        $names = File::where('parent_id', $dest->id)->pluck('name')->sort()->values()->all();
        $this->assertSame(['a (copy).txt', 'a.txt'], $names);
    }

    public function test_create_folder_still_blocks_duplicates_under_lock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('folders.create'), ['folder_name' => 'Same'])->assertRedirect();
        $this->actingAs($user)->post(route('folders.create'), ['folder_name' => 'Same'])->assertSessionHasErrors('name');

        $this->assertSame(1, File::where('name', 'Same')->where('is_dir', true)->count());
    }
}
