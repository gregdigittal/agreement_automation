<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class EntityFactory extends Factory
{
    protected $model = Entity::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'region_id' => Region::factory(),
            'name' => fake()->company(),
            'code' => strtoupper(fake()->unique()->lexify('??')),
        ];
    }

    public function withLegalDetails(): static
    {
        return $this->state(fn (array $attributes) => [
            'legal_name' => fake()->company() . ' ' . fake()->companySuffix(),
            'registration_number' => fake()->numerify('REG-####-###'),
            'registered_address' => fake()->address(),
        ]);
    }
}
