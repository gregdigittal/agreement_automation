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
}
