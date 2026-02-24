<?php

namespace Database\Factories;

use App\Models\RegulatoryFramework;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RegulatoryFrameworkFactory extends Factory
{
    protected $model = RegulatoryFramework::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'jurisdiction_code' => $this->faker->randomElement(['EU', 'US', 'AE', 'GB', 'GLOBAL']),
            'framework_name' => $this->faker->words(3, true).' Compliance Framework',
            'description' => $this->faker->sentence(),
            'is_active' => true,
            'requirements' => [
                [
                    'id' => 'req-1',
                    'text' => $this->faker->sentence(),
                    'category' => $this->faker->randomElement(['data_protection', 'financial', 'employment', 'other']),
                    'severity' => $this->faker->randomElement(['critical', 'high', 'medium', 'low']),
                ],
                [
                    'id' => 'req-2',
                    'text' => $this->faker->sentence(),
                    'category' => 'data_protection',
                    'severity' => 'medium',
                ],
            ],
        ];
    }
}
