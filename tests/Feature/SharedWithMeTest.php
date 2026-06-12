<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Group;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SharedWithMeTest extends TestCase
{
    use RefreshDatabase;

    private function grant(File $file, string $type, int $subjectId, string $ability = 'read'): void
    {
        Permission::create([
            'file_id' => $file->id,
            'subject_type' => $type,
            'subject_id' => $subjectId,
            'ability' => $ability,
        ]);
    }

    public function test_lists_files_and_folders_shared_with_me(): void
    {
        $owner = User::factory()->create(['name' => 'Owner']);
        $me = User::factory()->create();
        $sharedFile = File::factory()->for($owner, 'owner')->create(['name' => 'shared.txt']);
        $sharedFolder = File::factory()->for($owner, 'owner')->folder()->create(['name' => 'Shared Dir']);
        File::factory()->for($owner, 'owner')->create(['name' => 'private.txt']); // not shared
        File::factory()->for($me, 'owner')->create(['name' => 'mine.txt']);       // my own

        $this->grant($sharedFile, Permission::SUBJECT_USER, $me->id, 'read');
        $this->grant($sharedFolder, Permission::SUBJECT_USER, $me->id, 'write');

        $this->actingAs($me)->get('/shared')->assertInertia(
            fn (Assert $page) => $page
                ->component('Shared/Index')
                ->has('files', 1)
                ->where('files.0.name', 'shared.txt')
                ->where('files.0.owner', 'Owner')
                ->where('files.0.abilities.0', 'read')
                ->has('folders', 1)
                ->where('folders.0.name', 'Shared Dir')
        );
    }

    public function test_group_shares_appear(): void
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'team']);
        $member = User::factory()->create(['group_id' => $group->id]);
        $file = File::factory()->for($owner, 'owner')->create(['name' => 'team.txt']);
        $this->grant($file, Permission::SUBJECT_GROUP, $group->id, 'read');

        $this->actingAs($member)->get('/shared')->assertInertia(
            fn (Assert $page) => $page->has('files', 1)->where('files.0.name', 'team.txt')
        );
    }

    public function test_can_browse_a_shared_folder(): void
    {
        $owner = User::factory()->create();
        $me = User::factory()->create();
        $folder = File::factory()->for($owner, 'owner')->folder()->create(['name' => 'Docs']);
        File::factory()->for($owner, 'owner')->create(['name' => 'inside.txt', 'parent_id' => $folder->id]);
        $this->grant($folder, Permission::SUBJECT_USER, $me->id, 'read');

        $this->actingAs($me)->get("/shared/{$folder->id}")->assertInertia(
            fn (Assert $page) => $page
                ->component('Shared/Folder')
                ->where('current.name', 'Docs')
                ->has('files', 1)
                ->where('files.0.name', 'inside.txt')
        );
    }

    public function test_cannot_browse_a_folder_not_shared_with_me(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $folder = File::factory()->for($owner, 'owner')->folder()->create();

        $this->actingAs($other)->get("/shared/{$folder->id}")->assertForbidden();
    }

    public function test_shared_page_empty_for_user_with_no_grants(): void
    {
        $me = User::factory()->create();

        $this->actingAs($me)->get('/shared')->assertInertia(
            fn (Assert $page) => $page->has('files', 0)->has('folders', 0)
        );
    }
}
