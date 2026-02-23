<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\ContractKeyDate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractKeyDateFactory extends Factory
{
    protected $model = ContractKeyDate::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'contract_id' => Contract::factory(),
            'date_type' => fake()->randomElement(['expiry', 'renewal', 'start', 'termination']),
            'date_value' => fake()->dateTimeBetween('now', '+2 years')->format('Y-m-d'),
            'description' => fake()->sentence(),
            'is_verified' => false,
        ];
    }
}
