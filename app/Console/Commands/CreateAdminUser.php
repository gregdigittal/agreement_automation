<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'ccrs:create-admin {email} {--name= : Display name for the user}';
    protected $description = 'Create or promote a user to system_admin role';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?? explode('@', $email)[0];

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'id' => User::where('email', $email)->value('id') ?? Str::uuid()->toString(),
                'name' => $name,
            ]
        );

        $user->syncRoles(['system_admin']);

        $this->info("User '{$user->name}' ({$user->email}) is now system_admin.");
        $this->info("User ID: {$user->id}");

        return self::SUCCESS;
    }
}
