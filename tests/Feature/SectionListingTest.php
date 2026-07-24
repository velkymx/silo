<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SectionListingTest extends TestCase
{
    use RefreshDatabase;

    private function storedFile(User $owner, string $name = 'a.txt'): File
    {
        Storage::disk('public')->put($path = 'uploads/'.$owner->id.'/'.uniqid(), 'data');

        return File::factory()->for($owner, 'owner')->create(['name' => $name, 'path' => $path]);
    }

    public function test_section_all_is_the_default_folder_listing(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Docs']);

        $this->actingAs($user)->get('/?section=all')->assertInertia(
            fn (Assert $p) => $p->component('Files/Index')
                ->where('section', 'all')
                ->has('folders', 1)
                ->where('folders.0.name', 'Docs')
        );
    }

    public function test_section_trash_lists_only_own_trashed_roots(): void
    {
        Storage::fake('public');
        $me = User::factory()->create();
        $other = User::factory()->create();
        $mine = $this->storedFile($me, 'mine.txt');
        $theirs = $this->storedFile($other, 'theirs.txt');

        $this->actingAs($me)->delete(route('files.delete', $mine))->assertRedirect();
        $this->actingAs($other)->delete(route('files.delete', $theirs))->assertRedirect();

        $this->actingAs($me)->get('/?section=trash')->assertInertia(
            fn (Assert $p) => $p->component('Files/Index')
                ->where('section', 'trash')
                ->has('files', 1)
                ->where('files.0.id', $mine->id)
        );
    }

    public function test_section_shared_lists_shared_items_without_leaking(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $me = User::factory()->create();
        $shared = File::factory()->for($owner, 'owner')->create(['name' => 'shared.txt']);
        File::factory()->for($owner, 'owner')->create(['name' => 'private.txt']); // not shared — must not leak

        Permission::create([
            'file_id' => $shared->id,
            'subject_type' => Permission::SUBJECT_USER,
            'subject_id' => $me->id,
            'ability' => 'read',
        ]);

        $this->actingAs($me)->get('/?section=shared')->assertInertia(
            fn (Assert $p) => $p->component('Files/Index')
                ->where('section', 'shared')
                ->has('files', 1)
                ->where('files.0.name', 'shared.txt')
                ->where('files.0.owner', 'Owner')
        );
    }
}
