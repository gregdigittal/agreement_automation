<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Base class for all queue jobs.
 *
 * Provides a tenant context stub that is currently a no-op (single-tenant deployment).
 * When stancl/tenancy is installed, override or extend tenantId() to resolve the
 * active tenant from the queue payload, and add tenant initialisation in handle().
 *
 * Convention:
 *   - Override $tries, $backoff, and failed() in each subclass as needed.
 *   - Call $this->tenantId() in handle() where tenant-scoped queries are required.
 */
abstract class TenantAwareJob implements ShouldQueue
{
    use Queueable;

    /**
     * Returns the active tenant identifier.
     *
     * Stub — always null in the current single-tenant deployment.
     * To-do (P0-3 follow-up): resolve from the job payload when stancl/tenancy is installed.
     */
    protected function tenantId(): ?string
    {
        return null;
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
