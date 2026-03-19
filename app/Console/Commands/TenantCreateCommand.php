<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantCreateCommand extends Command
{
    protected $signature = 'tenant:create
        {--name= : The tenant display name}
        {--slug= : URL slug (lowercase alphanumeric and hyphens)}
        {--admin-email= : Optional initial admin email address}';

    protected $description = 'Provision a new tenant with its own database';

    public function handle(): int
    {
        $name = $this->option('name') ?? $this->ask('Tenant name');
        $slug = $this->option('slug') ?? $this->ask('Tenant slug (lowercase alphanumeric + hyphens)');

        if (! preg_match('/^[a-z0-9\-]+$/', (string) $slug)) {
            $this->error("Invalid slug '{$slug}'. Use lowercase letters, numbers, and hyphens only.");

            return self::FAILURE;
        }

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("A tenant with slug '{$slug}' already exists.");

            return self::FAILURE;
        }

        $domain = "{$slug}-ccrs.digittal.mobi";

        $this->info("Creating tenant '{$name}'...");
        $this->info("Domain: {$domain}");
        $this->info('Provisioning database and running migrations (synchronous)...');

        try {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $slug,
                'status' => Tenant::STATUS_ACTIVE,
            ]);

            $tenant->domains()->create(['domain' => $domain]);

            $adminEmail = $this->option('admin-email');

            $this->newLine();
            $this->info("<fg=green>✓ Tenant '{$name}' created successfully.</>");
            $this->table(['Field', 'Value'], [
                ['ID', $tenant->id],
                ['Name', $name],
                ['Slug', $slug],
                ['Domain', $domain],
                ['Admin email', $adminEmail ?? '(none — add manually)'],
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Failed to create tenant: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
