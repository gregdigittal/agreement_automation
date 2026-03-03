<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Expand contract_type enum (MySQL only — SQLite uses TEXT so any value is accepted)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contracts MODIFY COLUMN contract_type ENUM('Commercial', 'Merchant', 'Inter-Company') NOT NULL DEFAULT 'Commercial'");
        }

        // 2. Make counterparty_id nullable (inter-company contracts have no external counterparty)
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('counterparty_id', 36)->nullable()->change();
        });

        // 3. Add second_entity_id for the other group company in inter-company agreements
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('second_entity_id', 36)->nullable()->after('entity_id');

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->foreign('second_entity_id')
                    ->references('id')->on('entities')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->dropForeign(['second_entity_id']);
            }
            $table->dropColumn('second_entity_id');
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->char('counterparty_id', 36)->nullable(false)->change();
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contracts MODIFY COLUMN contract_type ENUM('Commercial', 'Merchant') NOT NULL DEFAULT 'Commercial'");
        }
    }
};
