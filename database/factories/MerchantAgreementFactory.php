<?php

namespace Database\Factories;

use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\MerchantAgreement;
use App\Models\Project;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

class MerchantAgreementFactory extends Factory
{
    protected $model = MerchantAgreement::class;

    public function definition(): array
    {
        $region = Region::factory()->create();
        $entity = Entity::factory()->create(['region_id' => $region->id]);
        $project = Project::factory()->create(['entity_id' => $entity->id]);

        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'counterparty_id' => Counterparty::factory()->create()->id,
            'region_id' => $region->id,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'merchant_fee' => fake()->randomFloat(2, 0, 10),
            'region_terms' => fake()->optional()->sentence(),
        ];
    }
}
