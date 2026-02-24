<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,              // Creates 6 roles (firstOrCreate — idempotent)
            ShieldPermissionSeeder::class,   // Syncs permissions per role (idempotent)
        ]);
    }
}
