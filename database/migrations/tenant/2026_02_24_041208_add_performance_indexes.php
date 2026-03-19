<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (! Schema::hasIndex('contracts', 'contracts_workflow_state_created_at_index')) {
                $table->index(['workflow_state', 'created_at']);
            }
            if (! Schema::hasIndex('contracts', 'contracts_counterparty_id_workflow_state_index')) {
                $table->index(['counterparty_id', 'workflow_state']);
            }
        });

        Schema::table('audit_log', function (Blueprint $table) {
            if (! Schema::hasIndex('audit_log', 'audit_log_actor_id_at_index')) {
                $table->index(['actor_id', 'at']);
            }
        });

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasIndex('notifications', 'notifications_recipient_user_id_status_created_at_index')) {
                $table->index(['recipient_user_id', 'status', 'created_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['workflow_state', 'created_at']);
            $table->dropIndex(['counterparty_id', 'workflow_state']);
        });
        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropIndex(['actor_id', 'at']);
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['recipient_user_id', 'status', 'created_at']);
        });
    }
};
