<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\SigningSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SigningSessionFactory extends Factory
{
    protected $model = SigningSession::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'initiated_by' => User::factory(),
            'signing_order' => 'sequential',
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the session is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }
}
