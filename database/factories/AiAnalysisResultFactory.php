<?php

namespace Database\Factories;

use App\Models\AiAnalysisResult;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiAnalysisResultFactory extends Factory
{
    protected $model = AiAnalysisResult::class;

    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'analysis_type' => $this->faker->randomElement(['summary', 'extraction', 'risk', 'deviation', 'obligations']),
            'status' => 'completed',
            'result' => ['text' => $this->faker->paragraph()],
            'evidence' => null,
            'confidence_score' => $this->faker->randomFloat(2, 0.5, 1.0),
            'model_used' => 'claude-sonnet-4-6',
            'token_usage_input' => $this->faker->numberBetween(100, 5000),
            'token_usage_output' => $this->faker->numberBetween(50, 2000),
            'cost_usd' => $this->faker->randomFloat(6, 0.001, 0.5),
            'processing_time_ms' => $this->faker->numberBetween(500, 30000),
        ];
    }
}
