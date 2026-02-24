<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->index(['workflow_state', 'created_at']);
            $table->index(['counterparty_id', 'workflow_state']);
            $table->index(['region_id', 'entity_id', 'project_id']);
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->fullText(['title']);
            }
        });

        Schema::table('audit_log', function (Blueprint $table) {
            $table->index(['actor_id', 'at']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['recipient_user_id', 'status', 'created_at']);
        });

    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['workflow_state', 'created_at']);
            $table->dropIndex(['counterparty_id', 'workflow_state']);
            $table->dropIndex(['region_id', 'entity_id', 'project_id']);
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                $table->dropFullText(['title']);
            }
        });
        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropIndex(['actor_id', 'at']);
        });
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['recipient_user_id', 'status', 'created_at']);
        });
    }
};
