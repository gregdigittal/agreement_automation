<?php

namespace Database\Factories;

use App\Models\KycTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class KycTemplateFactory extends Factory
{
    protected $model = KycTemplate::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'name' => fake()->words(3, true) . ' KYC Template',
            'entity_id' => null,
            'jurisdiction_id' => null,
            'contract_type_pattern' => fake()->optional()->randomElement(['Commercial', 'Merchant', '*']),
            'version' => 1,
            'status' => 'active',
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'archived']);
    }
}
