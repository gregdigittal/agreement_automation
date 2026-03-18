<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources\TenantResource\Pages;

use App\Filament\Platform\Resources\TenantResource;
use App\Models\Tenant;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    /**
     * After creating the Tenant record, provision its domain.
     * TenantCreated event fires CreateDatabase + MigrateDatabase automatically.
     */
    protected function afterCreate(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->record;

        $domain = "{$tenant->slug}-ccrs.digittal.mobi";

        if ($tenant->domains()->where('domain', $domain)->doesntExist()) {
            $tenant->domains()->create(['domain' => $domain]);
        }
    }
}
