<?php

namespace Database\Seeders;

use App\Models\ContractType;
use Illuminate\Database\Seeder;

class ContractTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Commercial',    'slug' => 'commercial',     'sort_order' => 10, 'description' => 'Standard commercial agreements (services, supply, NDA, consulting, etc.)'],
            ['name' => 'Merchant',      'slug' => 'merchant',       'sort_order' => 20, 'description' => 'Merchant processing and payment agreements.'],
            ['name' => 'Inter-Company', 'slug' => 'inter-company',  'sort_order' => 30, 'description' => 'Agreements between two entities within the Digittal Group.'],
        ];

        foreach ($types as $type) {
            ContractType::firstOrCreate(
                ['name' => $type['name']],
                [
                    'slug' => $type['slug'],
                    'sort_order' => $type['sort_order'],
                    'description' => $type['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
