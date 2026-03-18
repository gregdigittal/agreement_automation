<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAdmin>
 */
class PlatformAdminFactory extends Factory
{
    protected $model = PlatformAdmin::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
        ];
    }
}
