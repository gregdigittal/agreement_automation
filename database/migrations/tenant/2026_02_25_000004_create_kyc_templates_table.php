<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_templates', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->string('name', 255);
            $table->char('entity_id', 36)->nullable();
            $table->char('jurisdiction_id', 36)->nullable();
            $table->string('contract_type_pattern', 100)->default('*');
            $table->integer('version')->default(1);
            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->timestamps();

            $table->foreign('entity_id')->references('id')->on('entities')->nullOnDelete();
            $table->foreign('jurisdiction_id')->references('id')->on('jurisdictions')->nullOnDelete();
            $table->index(['entity_id', 'jurisdiction_id', 'contract_type_pattern', 'status'], 'idx_kyc_matching');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_templates');
    }
};
