<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('signing_authority_project', function (Blueprint $table) {
            $table->uuid('signing_authority_id');
            $table->uuid('project_id');
            $table->primary(['signing_authority_id', 'project_id']);
            $table->foreign('signing_authority_id')->references('id')->on('signing_authority')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        // Migrate existing project_id data into pivot table
        DB::statement("
            INSERT IGNORE INTO signing_authority_project (signing_authority_id, project_id)
            SELECT id, project_id FROM signing_authority WHERE project_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_authority_project');
    }
};
