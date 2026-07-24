<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_action_filter_returns_only_matching_rows(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        AuditLog::create(['action' => 'file.upload', 'ip' => '127.0.0.1']);
        AuditLog::create(['action' => 'file.download', 'ip' => '127.0.0.1']);
        AuditLog::create(['action' => 'link.revoke', 'ip' => '127.0.0.1']);

        $this->actingAs($admin)->get('/audit?action=upload')->assertInertia(
            fn (Assert $p) => $p->has('logs', 1)->where('logs.0.action', 'file.upload')
        );
    }

    public function test_action_filter_percent_is_treated_as_literal(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        AuditLog::create(['action' => 'vault.100%', 'ip' => '127.0.0.1']);
        AuditLog::create(['action' => 'file.upload', 'ip' => '127.0.0.1']);

        // A bare '%' must only match logs whose action contains a literal '%'.
        $this->actingAs($admin)->get('/audit?action=' . urlencode('%'))->assertInertia(
            fn (Assert $p) => $p->has('logs', 1)->where('logs.0.action', 'vault.100%')
        );
    }

    public function test_date_filter_bounds_results(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $old = AuditLog::create(['action' => 'file.upload', 'ip' => '127.0.0.1']);
        $old->forceFill(['created_at' => now()->subDays(10)])->save();
        AuditLog::create(['action' => 'file.upload', 'ip' => '127.0.0.1']); // today

        $from = now()->subDays(2)->toDateString();
        $this->actingAs($admin)->get("/audit?from={$from}")->assertInertia(
            fn (Assert $p) => $p->has('logs', 1)
        );
    }
}
