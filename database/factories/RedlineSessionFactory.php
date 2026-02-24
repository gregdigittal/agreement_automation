<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\RedlineSession;
use App\Models\WikiContract;
use Illuminate\Database\Eloquent\Factories\Factory;

class RedlineSessionFactory extends Factory
{
    protected $model = RedlineSession::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'contract_id' => Contract::factory(),
            'wiki_contract_id' => WikiContract::factory(),
            'status' => 'pending',
            'created_by' => null,
            'total_clauses' => 0,
            'reviewed_clauses' => 0,
            'summary' => null,
            'error_message' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'total_clauses' => 10,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => 'failed',
            'error_message' => 'Test error message',
        ]);
    }
}
