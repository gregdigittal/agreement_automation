<?php

namespace Database\Factories;

use App\Models\Counterparty;
use App\Models\VendorUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorUserFactory extends Factory
{
    protected $model = VendorUser::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'counterparty_id' => Counterparty::factory(),
        ];
    }
}
