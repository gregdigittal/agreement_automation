<?php

namespace Database\Factories;

use App\Models\AiDiscoveryDraft;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AiDiscoveryDraftFactory extends Factory
{
    protected $model = AiDiscoveryDraft::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'contract_id' => Contract::factory(),
            'analysis_id' => Str::uuid()->toString(),
            'draft_type' => fake()->randomElement(['counterparty', 'entity', 'jurisdiction', 'governing_law']),
            'extracted_data' => ['name' => fake()->company()],
            'matched_record_id' => null,
            'matched_record_type' => null,
            'confidence' => fake()->randomFloat(2, 0.3, 0.99),
            'status' => 'pending',
        ];
    }

    public function counterparty(array $data = []): static
    {
        return $this->state(fn () => [
            'draft_type' => 'counterparty',
            'extracted_data' => array_merge([
                'legal_name' => fake()->company(),
                'registration_number' => fake()->numerify('REG-####'),
            ], $data),
        ]);
    }

    public function entity(array $data = []): static
    {
        return $this->state(fn () => [
            'draft_type' => 'entity',
            'extracted_data' => array_merge([
                'name' => fake()->company() . ' LLC',
                'registration_number' => fake()->numerify('ENT-####'),
            ], $data),
        ]);
    }

    public function jurisdiction(array $data = []): static
    {
        return $this->state(fn () => [
            'draft_type' => 'jurisdiction',
            'extracted_data' => array_merge([
                'name' => fake()->city() . ' International Financial Centre',
            ], $data),
        ]);
    }

    public function governingLaw(array $data = []): static
    {
        return $this->state(fn () => [
            'draft_type' => 'governing_law',
            'extracted_data' => array_merge([
                'name' => 'Laws of ' . fake()->country(),
            ], $data),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => 'rejected',
            'reviewed_at' => now(),
        ]);
    }
}
