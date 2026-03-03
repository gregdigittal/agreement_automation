<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CountrySeeder::class,            // Seeds 249 ISO 3166-1 alpha-2 countries (idempotent)
            RoleSeeder::class,               // Creates 6 roles (firstOrCreate — idempotent)
            ShieldPermissionSeeder::class,    // Syncs permissions per role (idempotent)
            RegulatoryFrameworkSeeder::class, // Seeds 3 regulatory frameworks (idempotent)
            GoverningLawSeeder::class,       // Seeds 20 common governing law options (idempotent)
        ]);
    }
}
