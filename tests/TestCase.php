<?php

namespace Tests;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['filemanager.disk' => 'public']);
        $this->withoutVite();
    }

    /** Create a regular user and authenticate as them. */
    protected function asUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $this->actingAs($user);
        return $user;
    }

    /** Create an admin user and authenticate as them. */
    protected function asAdmin(array $attrs = []): User
    {
        return $this->asUser(array_merge(['is_admin' => true], $attrs));
    }

    /** Create a file owned by $user. */
    protected function withFile(User $user, array $attrs = []): File
    {
        return File::factory()->for($user, 'owner')->create($attrs);
    }

    /** Create a folder owned by $user. */
    protected function withFolder(User $user, array $attrs = []): File
    {
        return File::factory()->for($user, 'owner')->folder()->create($attrs);
    }
}
