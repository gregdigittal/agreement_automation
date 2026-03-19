<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Expression;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('name');
            $table->enum('contract_type', ['Commercial', 'Merchant']);
            $table->uuid('region_id')->nullable();
            $table->uuid('entity_id')->nullable();
            $table->uuid('project_id')->nullable();
            $table->integer('version')->default(1);
            $table->enum('status', ['draft', 'published', 'deprecated'])->default('draft');
            $table->json('stages')->default(new Expression("('[]')"));
            $table->json('validation_errors')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
            $table->foreign('entity_id')->references('id')->on('entities')->nullOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->nullOnDelete();
            $table->unique(['name', 'version']);
            $table->index('status');
            $table->index('contract_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_templates');
    }
};
