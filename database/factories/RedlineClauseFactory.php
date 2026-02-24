<?php

namespace Database\Factories;

use App\Models\RedlineClause;
use App\Models\RedlineSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class RedlineClauseFactory extends Factory
{
    protected $model = RedlineClause::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'session_id' => RedlineSession::factory(),
            'clause_number' => fake()->numberBetween(1, 30),
            'clause_heading' => fake()->sentence(3),
            'original_text' => fake()->paragraphs(2, true),
            'suggested_text' => fake()->paragraphs(2, true),
            'change_type' => fake()->randomElement(['unchanged', 'addition', 'deletion', 'modification']),
            'ai_rationale' => fake()->sentence(),
            'confidence' => fake()->randomFloat(2, 0.5, 1.0),
            'status' => 'pending',
            'final_text' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'accepted',
            'final_text' => $attrs['suggested_text'] ?? fake()->paragraph(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => 'rejected',
            'final_text' => $attrs['original_text'] ?? fake()->paragraph(),
            'reviewed_at' => now(),
        ]);
    }
}
