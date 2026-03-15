<?php

namespace App\Helpers;

/**
 * Tenant-aware cache key generator.
 *
 * Prefixes cache keys with a tenant identifier to prevent cross-tenant cache
 * pollution in multi-tenant deployments.
 *
 * Current state: single-tenant — key() returns the plain key unchanged.
 * To-do (P0-3 follow-up): when stancl/tenancy is installed, prefix with
 * `tenant()->id` or equivalent to scope keys per tenant.
 */
class TenantCache
{
    /**
     * Return a tenant-scoped cache key.
     *
     * @param  string  $key  The base cache key (e.g. 'contract_types.options').
     */
    public static function key(string $key): string
    {
        // TODO(multi-tenancy): return tenant()->id . ':' . $key;
        return $key;
    }
}
