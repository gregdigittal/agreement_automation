<?php

namespace Database\Factories;

use App\Models\SigningSession;
use App\Models\SigningSessionSigner;
use Illuminate\Database\Eloquent\Factories\Factory;

class SigningSessionSignerFactory extends Factory
{
    protected $model = SigningSessionSigner::class;

    public function definition(): array
    {
        return [
            'signing_session_id' => SigningSession::factory(),
            'signer_name' => fake()->name(),
            'signer_email' => fake()->safeEmail(),
            'signer_type' => 'external',
            'signing_order' => 0,
            'status' => 'pending',
        ];
    }
}
