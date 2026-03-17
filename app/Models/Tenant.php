<?php

namespace App\Models;

use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Custom data fields stored in the JSON `data` column.
     * Access via $tenant->name, $tenant->slug, etc.
     */
    public static function getCustomColumns(): array
    {
        return ['name', 'slug', 'status'];
    }

    /**
     * Statuses a tenant can be in.
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_PROVISIONING = 'provisioning';

    /**
     * The primary domain for this tenant (e.g. acme-ccrs.digittal.mobi).
     */
    public function primaryDomain(): ?string
    {
        return $this->domains->first()?->domain;
    }
}
