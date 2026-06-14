<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The suite fakes the "public" disk (Storage::fake('public')); pin the
        // file-manager disk to it so controllers store where the tests assert.
        // Production defaults to the private "local" disk (see C1 / config).
        config(['filemanager.disk' => 'public']);
    }
}
