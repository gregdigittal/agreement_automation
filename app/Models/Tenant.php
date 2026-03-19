<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * Real DB columns on the `tenants` table (everything else goes into `data` JSON).
     *
     * VirtualColumn requires ALL real DB columns to be listed here so it knows
     * which attributes to leave as top-level columns vs. serialise into `data`.
     * The base class only lists `id`; we add the CCRS-specific columns defined
     * in the create_tenants_table migration.
     */
    public static function getCustomColumns(): array
    {
        return ['id', 'name', 'slug', 'status'];
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
