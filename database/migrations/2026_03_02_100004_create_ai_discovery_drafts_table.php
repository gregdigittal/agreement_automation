<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_discovery_drafts', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('contract_id', 36);
            $table->char('analysis_id', 36)->nullable();
            $table->string('draft_type', 50);  // counterparty, entity, jurisdiction, governing_law
            $table->json('extracted_data');     // raw fields extracted by AI
            $table->char('matched_record_id', 36)->nullable();
            $table->string('matched_record_type', 100)->nullable();
            $table->float('confidence')->default(0);
            $table->enum('status', ['pending', 'approved', 'rejected', 'merged'])->default('pending');
            $table->char('reviewed_by', 36)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            }
            $table->index(['contract_id', 'status']);
            $table->index('draft_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_discovery_drafts');
    }
};
