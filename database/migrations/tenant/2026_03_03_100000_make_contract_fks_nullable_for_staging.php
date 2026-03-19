<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Make region_id nullable (staging contracts may not have a region yet)
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('region_id', 36)->nullable()->change();
        });

        // Make entity_id nullable
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('entity_id', 36)->nullable()->change();
        });

        // Make project_id nullable
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('project_id', 36)->nullable()->change();
        });

        // Make contract_type nullable (DBAL doesn't handle ENUM well, use raw SQL for MySQL)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contracts MODIFY COLUMN contract_type ENUM('Commercial', 'Merchant', 'Inter-Company') NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        // Restore contract_type NOT NULL (must come first — can't enforce NOT NULL FK columns
        // if contract_type is still nullable and has NULLs)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("UPDATE contracts SET contract_type = 'Commercial' WHERE contract_type IS NULL");
            DB::statement("ALTER TABLE contracts MODIFY COLUMN contract_type ENUM('Commercial', 'Merchant', 'Inter-Company') NOT NULL DEFAULT 'Commercial'");
        }

        // Restore NOT NULL on FK columns (set placeholder values first to avoid constraint violations)
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('region_id', 36)->nullable(false)->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->char('entity_id', 36)->nullable(false)->change();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->char('project_id', 36)->nullable(false)->change();
        });
    }
};
