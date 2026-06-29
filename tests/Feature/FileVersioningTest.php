<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use App\Services\FileVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FileVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_snapshot_at_returns_carbon_instance(): void
    {
        // ME-05: must be Carbon|null, not a raw string/DateTime that downstream
        // callers can't safely chain (->lt, ->diffInMinutes) onto.
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['name' => 'a.md', 'parent_id' => null]);
        FileVersion::create([
            'file_id' => $file->id,
            'version' => 2,
            'name' => 'a.md',
            'path' => 'a.md',
            'disk' => 'public',
            'size' => 10,
            'hash' => 'x',
            'created_by' => $user->id,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $at = app(FileVersioning::class)->lastSnapshotAt($file);

        $this->assertInstanceOf(Carbon::class, $at);
        $this->assertLessThan(now(), $at);
    }

    public function test_last_snapshot_at_returns_null_when_no_versions(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['name' => 'a.md', 'parent_id' => null]);

        $this->assertNull(app(FileVersioning::class)->lastSnapshotAt($file));
    }
}
