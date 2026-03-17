<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('merchant_agreements', function (Blueprint $table) {
            $table->string('agreement_type', 50)->nullable()->after('id');
            $table->uuid('wiki_contract_id')->nullable()->after('project_id');
            $table->uuid('governing_law_id')->nullable()->after('wiki_contract_id');
            $table->json('jurisdiction_ids')->nullable()->after('governing_law_id');
            $table->json('additional_counterparty_ids')->nullable()->after('jurisdiction_ids');
            $table->text('description')->nullable()->after('region_terms');

            $table->foreign('wiki_contract_id')->references('id')->on('wiki_contracts')->nullOnDelete();
            $table->foreign('governing_law_id')->references('id')->on('governing_laws')->nullOnDelete();
        });

        // Back-fill existing rows as Merchant type
        DB::table('merchant_agreements')
            ->whereNull('agreement_type')
            ->update(['agreement_type' => 'Merchant']);
    }

    public function down(): void
    {
        Schema::table('merchant_agreements', function (Blueprint $table) {
            $table->dropForeign(['wiki_contract_id']);
            $table->dropForeign(['governing_law_id']);
            $table->dropColumn([
                'agreement_type',
                'wiki_contract_id',
                'governing_law_id',
                'jurisdiction_ids',
                'additional_counterparty_ids',
                'description',
            ]);
        });
    }
};
