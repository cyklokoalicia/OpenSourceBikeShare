<?php

namespace Tests;

use Spatie\Permission\PermissionRegistrar;

abstract class DbTestWithSeededPermission extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
        $this->seed('TestingDatabaseSeeder');
        // Required otherwise permissions are not cached - bug in the library?
        app(PermissionRegistrar::class)->registerPermissions();
    }
}
