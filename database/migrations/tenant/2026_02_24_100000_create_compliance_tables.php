<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulatory_frameworks', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->string('jurisdiction_code', 10);
            $table->string('framework_name');
            $table->text('description')->nullable();
            $table->json('requirements');
            $table->boolean('is_active')->default(true);
            $table->char('created_by', 36)->nullable();
            $table->timestamps();

            $table->index('jurisdiction_code');
            $table->index('is_active');
        });

        Schema::create('compliance_findings', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('contract_id', 36);
            $table->char('framework_id', 36);
            $table->string('requirement_id', 100);
            $table->text('requirement_text');
            $table->enum('status', ['compliant', 'non_compliant', 'unclear', 'not_applicable'])->default('unclear');
            $table->text('evidence_clause')->nullable();
            $table->integer('evidence_page')->nullable();
            $table->text('ai_rationale')->nullable();
            $table->double('confidence')->nullable();
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('framework_id')->references('id')->on('regulatory_frameworks')->restrictOnDelete();
            $table->index('contract_id');
            $table->index(['contract_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_findings');
        Schema::dropIfExists('regulatory_frameworks');
    }
};
