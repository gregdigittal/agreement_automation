<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\Reminder;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        return [
            'id' => \Illuminate\Support\Str::uuid()->toString(),
            'contract_id' => Contract::factory(),
            'key_date_id' => null,
            'reminder_type' => 'expiry',
            'lead_days' => 30,
            'channel' => 'email',
            'recipient_email' => fake()->safeEmail(),
            'is_active' => true,
            'next_due_at' => now(),
        ];
    }
}
