<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('merchant_agreement_inputs', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->uuid('template_id')->nullable();
            $table->string('vendor_name');
            $table->string('merchant_fee')->nullable();
            $table->json('region_terms')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('wiki_contracts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_agreement_inputs');
    }
};
