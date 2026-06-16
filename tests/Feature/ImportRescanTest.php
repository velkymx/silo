<?php

namespace Tests\Feature;

use App\Jobs\ImportScan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportRescanTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_trigger_a_rescan(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/import/rescan', ['name' => 'Shared Drive'])
            ->assertRedirect();

        Queue::assertPushed(fn (ImportScan $job) => $job->ownerId === $admin->id && $job->name === 'Shared Drive');
    }

    public function test_non_admin_cannot_rescan_or_view(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/import')->assertForbidden();
        $this->actingAs($user)->post('/import/rescan')->assertForbidden();
    }
}
