<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central audit log for platform-level actions.
 *
 * Distinct from the per-tenant audit_log table.
 * Records impersonation events, tenant provisioning, and platform admin actions.
 * Intentionally separate from tenant databases so audit trail cannot be tampered
 * with by tenant-scoped code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('platform_admin_id')->nullable()->index();
            $table->string('event');                      // e.g. tenant.impersonated, tenant.created, tenant.suspended
            $table->string('tenant_id')->nullable();      // which tenant was affected
            $table->json('metadata')->nullable();         // extra context (IP, user-agent, etc.)
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — this table is append-only
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audit_log');
    }
};
