<?php

namespace Database\Factories;

use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ComplianceFindingFactory extends Factory
{
    protected $model = ComplianceFinding::class;

    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'contract_id' => Contract::factory(),
            'framework_id' => RegulatoryFramework::factory(),
            'requirement_id' => 'req-'.$this->faker->numberBetween(1, 100),
            'requirement_text' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['compliant', 'non_compliant', 'unclear', 'not_applicable']),
            'evidence_clause' => $this->faker->optional()->sentence(),
            'evidence_page' => $this->faker->optional()->numberBetween(1, 50),
            'ai_rationale' => $this->faker->sentence(),
            'confidence' => $this->faker->randomFloat(2, 0.3, 1.0),
        ];
    }
}
