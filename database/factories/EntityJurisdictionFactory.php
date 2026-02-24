<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\EntityJurisdiction;
use App\Models\Jurisdiction;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntityJurisdictionFactory extends Factory
{
    protected $model = EntityJurisdiction::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'entity_id' => Entity::factory(),
            'jurisdiction_id' => Jurisdiction::factory(),
            'license_number' => fake()->optional()->numerify('LIC-####'),
            'license_expiry' => fake()->optional()->dateTimeBetween('+1 month', '+3 years'),
            'is_primary' => false,
        ];
    }
}
