<?php

namespace Database\Factories;

use App\Models\Jurisdiction;
use Illuminate\Database\Eloquent\Factories\Factory;

class JurisdictionFactory extends Factory
{
    protected $model = Jurisdiction::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => fake()->unique()->city() . ' - ' . fake()->word(),
            'country_code' => fake()->countryCode(),
            'regulatory_body' => fake()->company(),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
