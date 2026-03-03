<?php

namespace Database\Seeders;

use App\Models\GoverningLaw;
use Illuminate\Database\Seeder;

class GoverningLawSeeder extends Seeder
{
    public function run(): void
    {
        $laws = [
            ['name' => 'England and Wales', 'country_code' => 'GB', 'legal_system' => 'Common Law'],
            ['name' => 'New York', 'country_code' => 'US', 'legal_system' => 'Common Law'],
            ['name' => 'Delaware', 'country_code' => 'US', 'legal_system' => 'Common Law'],
            ['name' => 'California', 'country_code' => 'US', 'legal_system' => 'Common Law'],
            ['name' => 'UAE Federal', 'country_code' => 'AE', 'legal_system' => 'Civil Law'],
            ['name' => 'DIFC', 'country_code' => 'AE', 'legal_system' => 'Common Law'],
            ['name' => 'ADGM', 'country_code' => 'AE', 'legal_system' => 'Common Law'],
            ['name' => 'Singapore', 'country_code' => 'SG', 'legal_system' => 'Common Law'],
            ['name' => 'Hong Kong', 'country_code' => 'HK', 'legal_system' => 'Common Law'],
            ['name' => 'France', 'country_code' => 'FR', 'legal_system' => 'Civil Law'],
            ['name' => 'Germany', 'country_code' => 'DE', 'legal_system' => 'Civil Law'],
            ['name' => 'Switzerland', 'country_code' => 'CH', 'legal_system' => 'Civil Law'],
            ['name' => 'Saudi Arabia', 'country_code' => 'SA', 'legal_system' => 'Sharia / Civil Law'],
            ['name' => 'Bahrain', 'country_code' => 'BH', 'legal_system' => 'Mixed'],
            ['name' => 'India', 'country_code' => 'IN', 'legal_system' => 'Common Law'],
            ['name' => 'Australia (NSW)', 'country_code' => 'AU', 'legal_system' => 'Common Law'],
            ['name' => 'South Africa', 'country_code' => 'ZA', 'legal_system' => 'Mixed'],
            ['name' => 'Nigeria', 'country_code' => 'NG', 'legal_system' => 'Common Law'],
            ['name' => 'Kenya', 'country_code' => 'KE', 'legal_system' => 'Common Law'],
            ['name' => 'Egypt', 'country_code' => 'EG', 'legal_system' => 'Civil Law'],
        ];

        foreach ($laws as $law) {
            GoverningLaw::firstOrCreate(
                ['name' => $law['name']],
                $law
            );
        }
    }
}
