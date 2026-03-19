<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fix users.id column type from VARCHAR(255) to CHAR(36).
 *
 * The original migration (000025) used string('id') which creates VARCHAR(255).
 * All FK columns referencing users.id use CHAR(36). This migration aligns
 * the PK type with its FK references.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only run on MySQL — SQLite (used in tests) doesn't have this issue
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Check if the column is already CHAR(36) (e.g. from a fresh migrate)
        $columnType = DB::selectOne(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'users'
               AND COLUMN_NAME = 'id'"
        );

        if (! $columnType || $columnType->COLUMN_TYPE === 'char(36)') {
            return; // Already correct
        }

        // Drop FK constraints that reference users.id
        $fksToDrop = [
            ['table' => 'kyc_pack_items', 'fk' => 'kyc_pack_items_attested_by_foreign'],
            ['table' => 'kyc_pack_items', 'fk' => 'kyc_pack_items_completed_by_foreign'],
            ['table' => 'signing_sessions', 'fk' => 'signing_sessions_initiated_by_foreign'],
            ['table' => 'contract_user_access', 'fk' => 'contract_user_access_user_id_foreign'],
            ['table' => 'contract_user_access', 'fk' => 'contract_user_access_granted_by_foreign'],
            ['table' => 'stored_signatures', 'fk' => 'stored_signatures_user_id_foreign'],
        ];

        foreach ($fksToDrop as $fk) {
            if (Schema::hasTable($fk['table'])) {
                try {
                    Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                        $table->dropForeign($fk['fk']);
                    });
                } catch (\Exception $e) {
                    // FK may not exist yet — skip
                }
            }
        }

        // Alter users.id from VARCHAR(255) to CHAR(36)
        DB::statement('ALTER TABLE users MODIFY id CHAR(36) NOT NULL');

        // Re-add FK constraints
        $fksToAdd = [
            ['table' => 'kyc_pack_items', 'column' => 'attested_by', 'onDelete' => 'SET NULL'],
            ['table' => 'kyc_pack_items', 'column' => 'completed_by', 'onDelete' => 'SET NULL'],
            ['table' => 'signing_sessions', 'column' => 'initiated_by', 'onDelete' => 'SET NULL'],
            ['table' => 'contract_user_access', 'column' => 'user_id', 'onDelete' => 'CASCADE'],
            ['table' => 'contract_user_access', 'column' => 'granted_by', 'onDelete' => 'SET NULL'],
            ['table' => 'stored_signatures', 'column' => 'user_id', 'onDelete' => 'CASCADE'],
        ];

        foreach ($fksToAdd as $fk) {
            if (Schema::hasTable($fk['table'])) {
                try {
                    Schema::table($fk['table'], function (Blueprint $table) use ($fk) {
                        $builder = $table->foreign($fk['column'])->references('id')->on('users');
                        match ($fk['onDelete']) {
                            'CASCADE' => $builder->cascadeOnDelete(),
                            'SET NULL' => $builder->nullOnDelete(),
                            default => null,
                        };
                    });
                } catch (\Exception $e) {
                    // FK may already exist — skip
                }
            }
        }
    }

    public function down(): void
    {
        // Reverting to VARCHAR(255) is not useful — leave as CHAR(36)
    }
};
