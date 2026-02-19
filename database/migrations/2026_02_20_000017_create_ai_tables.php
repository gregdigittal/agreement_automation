<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_analysis_results', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->enum('analysis_type', ['summary', 'extraction', 'risk', 'deviation', 'obligations']);
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->json('result')->nullable();
            $table->json('evidence')->nullable();
            $table->double('confidence_score')->nullable();
            $table->string('model_used')->nullable();
            $table->integer('token_usage_input')->nullable();
            $table->integer('token_usage_output')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->decimal('agent_budget_usd', 10, 4)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->index('contract_id');
            $table->index('analysis_type');
            $table->index('status');
        });

        Schema::create('ai_extracted_fields', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->uuid('analysis_id');
            $table->string('field_name', 100);
            $table->text('field_value')->nullable();
            $table->text('evidence_clause')->nullable();
            $table->integer('evidence_page')->nullable();
            $table->double('confidence')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('analysis_id')->references('id')->on('ai_analysis_results')->cascadeOnDelete();
            $table->index('contract_id');
            $table->index('analysis_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_extracted_fields');
        Schema::dropIfExists('ai_analysis_results');
    }
};
