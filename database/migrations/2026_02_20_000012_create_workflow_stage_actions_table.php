<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_stage_actions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('instance_id');
            $table->string('stage_name', 100);
            $table->enum('action', ['approve', 'reject', 'rework', 'skip']);
            $table->string('actor_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->text('comment')->nullable();
            $table->json('artifacts')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('instance_id')->references('id')->on('workflow_instances')->cascadeOnDelete();
            $table->index('instance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_stage_actions');
    }
};
