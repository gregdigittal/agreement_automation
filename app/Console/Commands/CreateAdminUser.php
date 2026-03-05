<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'ccrs:create-admin {email} {--name= : Display name for the user} {--password= : Set a login password}';
    protected $description = 'Create or promote a user to system_admin role';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->option('name') ?? explode('@', $email)[0];
        $password = $this->option('password');

        $data = [
            'id' => User::where('email', $email)->value('id') ?? Str::uuid()->toString(),
            'name' => $name,
            'status' => 'active',
        ];

        if ($password) {
            $data['password'] = $password;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            $data,
        );

        $user->syncRoles(['system_admin']);

        $this->info("User '{$user->name}' ({$user->email}) is now system_admin.");
        $this->info("User ID: {$user->id}");

        if ($password) {
            $this->info('Password has been set — this user can now log in with email/password.');
        }

        return self::SUCCESS;
    }
}
