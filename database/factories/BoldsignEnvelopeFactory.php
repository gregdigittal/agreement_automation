<?php

namespace Database\Factories;

use App\Models\BoldsignEnvelope;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

class BoldsignEnvelopeFactory extends Factory
{
    protected $model = BoldsignEnvelope::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'contract_id' => Contract::factory(),
            'boldsign_document_id' => 'doc-' . fake()->unique()->uuid(),
            'status' => 'draft',
            'signing_order' => 'sequential',
            'signers' => [],
        ];
    }
}
