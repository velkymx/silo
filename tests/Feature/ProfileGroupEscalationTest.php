<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileGroupEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_change_own_group_via_profile_update(): void
    {
        $own = Group::create(['name' => 'Members']);
        $privileged = Group::create(['name' => 'Admins']);
        $user = User::factory()->create(['group_id' => $own->id]);

        $this->actingAs($user)->post('/profile', [
            'name' => 'New Name',
            'email' => $user->email,
            'group_id' => $privileged->id,
        ])->assertRedirect();

        // Name updates, but the group assignment is ignored.
        $this->assertSame('New Name', $user->fresh()->name);
        $this->assertSame($own->id, $user->fresh()->group_id);
    }
}
