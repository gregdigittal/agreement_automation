<?php

namespace Database\Factories;

use App\Models\Counterparty;
use Illuminate\Database\Eloquent\Factories\Factory;

class CounterpartyFactory extends Factory
{
    protected $model = Counterparty::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'legal_name' => fake()->company(),
            'registration_number' => fake()->optional()->numerify('########'),
            'jurisdiction' => fake()->countryCode(),
            'status' => 'Active',
            'preferred_language' => 'en',
        ];
    }
}
