<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Seed Spatie roles for tests that use RefreshDatabase (migrations already ran)
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class))) {
            $this->seed(\Database\Seeders\RoleSeeder::class);
        }
    }
}
