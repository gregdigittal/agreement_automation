<?php

namespace Database\Factories;

use App\Models\Region;
use App\Models\WikiContract;
use Illuminate\Database\Eloquent\Factories\Factory;

class WikiContractFactory extends Factory
{
    protected $model = WikiContract::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => fake()->sentence(3),
            'category' => fake()->randomElement(['Commercial', 'Merchant', 'NDA', 'SLA']),
            'region_id' => Region::factory(),
            'version' => fake()->numberBetween(1, 10),
            'status' => 'draft',
            'storage_path' => 'templates/' . fake()->uuid() . '.docx',
            'file_name' => fake()->slug(2) . '.docx',
            'description' => fake()->paragraph(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
