<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('obligations_register', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->uuid('analysis_id')->nullable();
            $table->enum('obligation_type', ['reporting', 'sla', 'insurance', 'deliverable', 'payment', 'other']);
            $table->text('description');
            $table->date('due_date')->nullable();
            $table->enum('recurrence', ['once', 'daily', 'weekly', 'monthly', 'quarterly', 'annually'])->nullable();
            $table->string('responsible_party')->nullable();
            $table->enum('status', ['active', 'completed', 'waived', 'overdue'])->default('active');
            $table->text('evidence_clause')->nullable();
            $table->double('confidence')->nullable();
            $table->timestamps();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('analysis_id')->references('id')->on('ai_analysis_results')->nullOnDelete();
            $table->index('contract_id');
            $table->index('status');
            $table->index('due_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('obligations_register');
    }
};
