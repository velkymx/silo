<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function storedFile(User $owner): File
    {
        Storage::disk('public')->put($path = 'uploads/'.$owner->id.'/a.txt', 'data');

        return File::factory()->for($owner, 'owner')->create(['name' => 'a.txt', 'path' => $path]);
    }

    public function test_download_is_audited(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = $this->storedFile($user);

        $this->actingAs($user)->get(route('files.download', $file));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id, 'action' => 'file.download',
            'file_id' => $file->id, 'file_name' => 'a.txt',
        ]);
    }

    public function test_trash_and_grant_are_audited(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create(['email' => 'g@example.com']);
        $file = $this->storedFile($owner);

        $this->actingAs($owner)->postJson(route('files.permissions.store', $file), [
            'subject_type' => 'user', 'email' => 'g@example.com', 'abilities' => ['read'],
        ]);
        $this->actingAs($owner)->delete(route('files.delete', $file));

        $this->assertDatabaseHas('audit_logs', ['action' => 'permission.grant', 'file_id' => $file->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'file.trash', 'file_id' => $file->id]);
    }

    public function test_guest_link_download_is_audited_against_the_creator(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $file = $this->storedFile($owner);
        $link = ShareLink::factory()->create(['file_id' => $file->id, 'created_by' => $owner->id]);

        $this->get(route('shares.public.download', $link->token))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'link.download', 'file_id' => $file->id, 'user_id' => $owner->id,
        ]);
    }

    public function test_admin_can_view_audit_log_but_others_cannot(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/audit')->assertForbidden();
        $this->actingAs($admin)->get('/audit')->assertOk()->assertInertia(
            fn (Assert $p) => $p->component('Admin/Audit/Index')->has('logs')
        );
    }
}
