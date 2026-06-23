<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use App\Models\VaultItem;
use App\Services\VaultCrypto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_secret_is_encrypted_at_rest_and_decrypts_back(): void
    {
        $user = User::factory()->create();
        $item = VaultItem::factory()->for($user, 'owner')->create(['secret' => 'hunter2']);

        // Raw DB column must be ciphertext, not the plaintext.
        $raw = DB::table('vault_items')->where('id', $item->id)->value('secret');
        $this->assertNotSame('hunter2', $raw);
        $this->assertStringNotContainsString('hunter2', $raw);

        // Model round-trips to plaintext.
        $this->assertSame('hunter2', $item->fresh()->secret);
        $this->assertSame('hunter2', app(VaultCrypto::class)->decrypt($raw));
    }

    public function test_index_never_exposes_the_secret(): void
    {
        $user = User::factory()->create();
        VaultItem::factory()->for($user, 'owner')->create(['secret' => 'topsecret', 'name' => 'Prod DB']);

        $response = $this->actingAs($user)->get(route('vault.index'))->assertOk();
        $this->assertStringNotContainsString('topsecret', $response->getContent());
        $response->assertInertia(fn ($page) => $page->component('Vault/Index')->has('items', 1));
    }

    public function test_reveal_requires_the_correct_password_and_is_audited(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pw')]);
        $item = VaultItem::factory()->for($user, 'owner')->create(['secret' => 'reveal-me']);

        // Wrong password is rejected.
        $this->actingAs($user)->postJson(route('vault.reveal', $item), ['password' => 'nope'])
            ->assertStatus(422);

        // Correct password reveals + audits + no-store.
        $res = $this->actingAs($user)->postJson(route('vault.reveal', $item), ['password' => 'secret-pw'])
            ->assertOk()
            ->assertJsonPath('secret', 'reveal-me');
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'vault.reveal']);
    }

    public function test_group_member_can_view_but_not_edit_a_shared_secret(): void
    {
        $group = Group::create(['name' => 'Engineering']);
        $owner = User::factory()->create();
        $member = User::factory()->create(['group_id' => $group->id, 'password' => bcrypt('pw')]);
        $item = VaultItem::factory()->for($owner, 'owner')->create(['group_id' => $group->id, 'secret' => 's']);

        $this->actingAs($member)->postJson(route('vault.reveal', $item), ['password' => 'pw'])->assertOk();
        $this->actingAs($member)->put(route('vault.update', $item), ['name' => 'Hijack', 'secret' => 'x'])
            ->assertForbidden();
    }

    public function test_unrelated_user_cannot_reveal_or_edit(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create(['password' => bcrypt('pw')]);
        $item = VaultItem::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)->postJson(route('vault.reveal', $item), ['password' => 'pw'])->assertForbidden();
        $this->actingAs($other)->delete(route('vault.destroy', $item))->assertForbidden();
    }

    public function test_owner_can_create_update_and_rotate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('vault.store'), ['name' => 'GitHub', 'secret' => 'tok'])
            ->assertRedirect();
        $item = VaultItem::where('name', 'GitHub')->firstOrFail();
        $this->assertNotNull($item->last_rotated_at);

        // Updating without a secret keeps the old one.
        $this->actingAs($user)->put(route('vault.update', $item), ['name' => 'GitHub PAT'])->assertRedirect();
        $this->assertSame('tok', $item->fresh()->secret);
    }

    public function test_generate_returns_a_password(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('vault.generate', ['length' => 24]))
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('password', fn ($p) => strlen($p) === 24));
    }
}
