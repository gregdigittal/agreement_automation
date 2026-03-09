<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('contract_ref', 30)->nullable()->after('id');
            $table->unique('contract_ref');
            $table->index('contract_ref');
        });

        // Back-fill existing contracts with a reference
        $contracts = \App\Models\Contract::whereNull('contract_ref')
            ->orderBy('created_at')
            ->get();

        foreach ($contracts as $contract) {
            $contract->contract_ref = \App\Models\Contract::generateContractRef($contract->contract_type);
            $contract->saveQuietly();
        }
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropUnique(['contract_ref']);
            $table->dropIndex(['contract_ref']);
            $table->dropColumn('contract_ref');
        });
    }
};
