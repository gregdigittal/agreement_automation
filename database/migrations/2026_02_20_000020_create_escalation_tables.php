<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('escalation_rules', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('workflow_template_id');
            $table->string('stage_name', 100);
            $table->integer('sla_breach_hours');
            $table->integer('tier')->default(1);
            $table->string('escalate_to_role')->nullable();
            $table->string('escalate_to_user_id')->nullable();
            $table->timestamps();
            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->cascadeOnDelete();
            $table->index('workflow_template_id');
        });

        Schema::create('escalation_events', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('workflow_instance_id');
            $table->uuid('rule_id');
            $table->uuid('contract_id');
            $table->string('stage_name', 100);
            $table->integer('tier');
            $table->timestamp('escalated_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('workflow_instance_id')->references('id')->on('workflow_instances')->cascadeOnDelete();
            $table->foreign('rule_id')->references('id')->on('escalation_rules')->cascadeOnDelete();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->index('workflow_instance_id');
            $table->index(['contract_id', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('escalation_events');
        Schema::dropIfExists('escalation_rules');
    }
};
