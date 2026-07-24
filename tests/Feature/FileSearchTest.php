<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FileSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_files_across_all_folders(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'budget-report.xlsx', 'parent_id' => $folder->id]);
        File::factory()->for($user, 'owner')->create(['name' => 'holiday.png']);

        $this->actingAs($user)->get('/?search=report')->assertInertia(
            fn (Assert $page) => $page
                ->where('searching', true)
                ->has('files', 1)
                ->where('files.0.name', 'budget-report.xlsx')
                ->where('files.0.location.folder_id', $folder->id)
        );
    }

    public function test_search_matches_metadata(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create([
            'name' => 'IMG_001.jpg',
            'metadata' => ['camera_make' => 'Canon', 'camera_model' => 'EOS R5'],
        ]);
        File::factory()->for($user, 'owner')->create(['name' => 'IMG_002.jpg', 'metadata' => null]);

        $this->actingAs($user)->get('/?search=Canon')->assertInertia(
            fn (Assert $page) => $page->has('files', 1)->where('files.0.name', 'IMG_001.jpg')
        );
    }

    public function test_search_excludes_folders_and_other_users(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'reports']);
        File::factory()->for($other, 'owner')->create(['name' => 'reports.txt']);
        File::factory()->for($user, 'owner')->create(['name' => 'reports.txt']);

        $this->actingAs($user)->get('/?search=reports')->assertInertia(
            fn (Assert $page) => $page->has('files', 1)->where('files.0.name', 'reports.txt')
        );
    }

    public function test_search_ignores_trashed_files(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['name' => 'gone-report.txt']);
        $file->delete();

        $this->actingAs($user)->get('/?search=report')->assertInertia(
            fn (Assert $page) => $page->has('files', 0)
        );
    }

    public function test_search_percent_wildcard_is_treated_as_literal(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'sales-100%.csv']);
        File::factory()->for($user, 'owner')->create(['name' => 'unrelated.txt']);

        // A literal '%' query must match only the file whose name contains '%',
        // not every file owned by the user (which is what an un-escaped LIKE would return).
        $this->actingAs($user)->get('/?search=' . urlencode('%'))->assertInertia(
            fn (Assert $page) => $page->has('files', 1)->where('files.0.name', 'sales-100%.csv')
        );
    }

    public function test_search_underscore_wildcard_is_treated_as_literal(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'a_c.txt']);
        File::factory()->for($user, 'owner')->create(['name' => 'abc.txt']);

        // An un-escaped '_' acts as a single-char wildcard and would match 'abc' too.
        // Escaped, 'a_c' must match only the file with a literal underscore.
        $this->actingAs($user)->get('/?search=a_c')->assertInertia(
            fn (Assert $page) => $page->has('files', 1)->where('files.0.name', 'a_c.txt')
        );
    }
}
