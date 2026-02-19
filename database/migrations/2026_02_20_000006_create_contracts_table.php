<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('region_id');
            $table->uuid('entity_id');
            $table->uuid('project_id');
            $table->uuid('counterparty_id');
            $table->uuid('parent_contract_id')->nullable();
            $table->enum('contract_type', ['Commercial', 'Merchant']);
            $table->string('title')->nullable();
            $table->string('workflow_state', 50)->default('draft');
            $table->string('signing_status', 50)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->integer('file_version')->default(1);
            $table->string('sharepoint_url')->nullable();
            $table->string('sharepoint_version')->nullable();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
            $table->foreign('region_id')->references('id')->on('regions')->restrictOnDelete();
            $table->foreign('entity_id')->references('id')->on('entities')->restrictOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->restrictOnDelete();
            $table->foreign('counterparty_id')->references('id')->on('counterparties')->restrictOnDelete();
            $table->foreign('parent_contract_id')->references('id')->on('contracts')->nullOnDelete();
            $table->index(['region_id', 'entity_id', 'project_id']);
            $table->index('counterparty_id');
            $table->index('workflow_state');
            $table->fullText(['title', 'contract_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
