<?php

namespace Database\Factories;

use App\Models\KycTemplateItem;
use App\Models\KycTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class KycTemplateItemFactory extends Factory
{
    protected $model = KycTemplateItem::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'kyc_template_id' => KycTemplate::factory(),
            'sort_order' => fake()->numberBetween(0, 20),
            'label' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'field_type' => fake()->randomElement(['text', 'file_upload', 'textarea', 'number', 'date', 'yes_no', 'select', 'attestation']),
            'is_required' => true,
            'options' => null,
            'validation_rules' => null,
        ];
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes) => ['is_required' => false]);
    }

    public function fileUpload(): static
    {
        return $this->state(fn (array $attributes) => ['field_type' => 'file_upload']);
    }

    public function text(): static
    {
        return $this->state(fn (array $attributes) => ['field_type' => 'text']);
    }

    public function attestation(): static
    {
        return $this->state(fn (array $attributes) => ['field_type' => 'attestation']);
    }
}
