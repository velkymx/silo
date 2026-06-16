<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingRoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_scalar_values_round_trip_unmangled(): void
    {
        Setting::put('backup.frequency', 'daily');
        Setting::put('backup.retention', 7);

        // The classic bug: reading back '"daily"' instead of 'daily' breaks the
        // scheduler's === 'daily' comparison.
        $this->assertSame('daily', Setting::get('backup.frequency'));
        $this->assertSame(7, Setting::get('backup.retention'));
        $this->assertSame('fallback', Setting::get('missing.key', 'fallback'));
    }
}
