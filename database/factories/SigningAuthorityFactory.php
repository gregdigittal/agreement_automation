<?php

namespace Database\Factories;

use App\Models\Entity;
use App\Models\Project;
use App\Models\SigningAuthority;
use Illuminate\Database\Eloquent\Factories\Factory;

class SigningAuthorityFactory extends Factory
{
    protected $model = SigningAuthority::class;

    public function definition(): array
    {
        $entity = Entity::factory()->create();
        $project = Project::factory()->create(['entity_id' => $entity->id]);

        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'user_id' => (string) fake()->uuid(),
            'user_email' => fake()->safeEmail(),
            'role_or_name' => fake()->name(),
            'contract_type_pattern' => 'Merchant',
        ];
    }
}
