<?php

namespace App\Helpers;

/**
 * Tenant-aware cache key generator.
 *
 * Prefixes cache keys with the active tenant's ID to prevent cross-tenant
 * cache pollution. Falls back to the plain key in central context (no active
 * tenant) so existing central-context code continues to work unchanged.
 */
class TenantCache
{
    /**
     * Return a tenant-scoped cache key.
     *
     * When tenancy is initialised (i.e. a tenant request), the key is prefixed
     * with `{tenantId}:` so each tenant gets its own cache namespace.
     * In central context (platform panel, artisan commands, queue workers
     * dispatched outside a tenant) the plain key is returned unchanged.
     *
     * @param  string  $key  The base cache key (e.g. 'contract_types.options').
     */
    public static function key(string $key): string
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return tenancy()->tenant->getTenantKey().':'.$key;
        }

        return $key;
    }
}
