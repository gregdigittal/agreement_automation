<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Create the contract_types lookup table
        Schema::create('contract_types', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('name', 100)->unique();
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Convert contracts.contract_type from ENUM to VARCHAR(100)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE contracts MODIFY COLUMN contract_type VARCHAR(100) NULL DEFAULT NULL");
            // Add index for filter performance
            DB::statement("CREATE INDEX contracts_contract_type_index ON contracts (contract_type)");
        }

        // 3. Convert workflow_templates.contract_type from ENUM to VARCHAR(100)
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE workflow_templates DROP INDEX workflow_templates_contract_type_index");
            DB::statement("ALTER TABLE workflow_templates MODIFY COLUMN contract_type VARCHAR(100) NOT NULL");
            DB::statement("ALTER TABLE workflow_templates ADD INDEX workflow_templates_contract_type_index (contract_type)");
        }
    }

    public function down(): void
    {
        // Revert workflow_templates
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE workflow_templates DROP INDEX workflow_templates_contract_type_index");
            DB::statement("ALTER TABLE workflow_templates MODIFY COLUMN contract_type ENUM('Commercial', 'Merchant', 'Inter-Company') NOT NULL");
            DB::statement("ALTER TABLE workflow_templates ADD INDEX workflow_templates_contract_type_index (contract_type)");
        }

        // Revert contracts
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("DROP INDEX contracts_contract_type_index ON contracts");
            DB::statement("UPDATE contracts SET contract_type = 'Commercial' WHERE contract_type NOT IN ('Commercial', 'Merchant', 'Inter-Company')");
            DB::statement("ALTER TABLE contracts MODIFY COLUMN contract_type ENUM('Commercial', 'Merchant', 'Inter-Company') NULL DEFAULT NULL");
        }

        Schema::dropIfExists('contract_types');
    }
};
