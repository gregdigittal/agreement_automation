<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'entity_id' => Entity::factory(),
            'name' => fake()->words(2, true),
            'code' => strtoupper(fake()->unique()->lexify('???')),
        ];
    }
}
