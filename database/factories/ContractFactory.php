<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContractFactory extends Factory
{
    protected $model = Contract::class;

    protected array $stateFields = [];

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'region_id' => Region::factory(),
            'entity_id' => Entity::factory(),
            'project_id' => Project::factory(),
            'counterparty_id' => Counterparty::factory(),
            'contract_type' => fake()->randomElement(['Commercial', 'Merchant']),
            'title' => fake()->sentence(4),
            'storage_path' => 'contracts/' . fake()->uuid() . '.pdf',
            'file_name' => fake()->slug(2) . '.pdf',
            'file_version' => 1,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Contract $contract) {
            $contract->workflow_state = $contract->workflow_state ?? 'draft';
            $contract->signing_status = $contract->signing_status ?? 'not_sent';
            $contract->saveQuietly();
        });
    }

    public function withState(string $workflowState): static
    {
        return $this->afterCreating(function (Contract $contract) use ($workflowState) {
            $contract->workflow_state = $workflowState;
            $contract->saveQuietly();
        });
    }
}
