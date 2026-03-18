<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Base class for all queue jobs.
 *
 * Tenant context is propagated automatically by QueueTenancyBootstrapper:
 *   - When dispatched from a tenant context, the bootstrapper stores the tenant ID
 *     in the job payload and re-initializes tenancy before handle() runs.
 *   - Jobs dispatched from central context can target a specific tenant via
 *     withTenant() — tenantId() will then return that tenant's key.
 *
 * Convention: override $tries, $backoff, and failed() in each subclass.
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Queueable;

    /** Explicit tenant ID for central-context dispatching. */
    protected ?string $tenantId = null;

    /**
     * Returns the active tenant identifier.
     *
     * Priority: explicit $tenantId → current tenancy context → null.
     */
    protected function tenantId(): ?string
    {
        if ($this->tenantId !== null) {
            return $this->tenantId;
        }

        return tenancy()->initialized ? tenancy()->tenant->getTenantKey() : null;
    }

    /**
     * Pin this job to a specific tenant for central-context dispatching.
     */
    public function withTenant(Tenant $tenant): static
    {
        $this->tenantId = $tenant->getTenantKey();

        return $this;
    }

    /**
     * Default failed handler. Subclasses should override this with job-specific logic.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error(static::class.': job failed', [
            'exception' => $exception->getMessage(),
            'tenant_id' => $this->tenantId(),
        ]);
    }
}
