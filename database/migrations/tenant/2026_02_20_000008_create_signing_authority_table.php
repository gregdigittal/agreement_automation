<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('signing_authority', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('entity_id');
            $table->uuid('project_id')->nullable();
            $table->string('user_id');
            $table->string('user_email')->nullable();
            $table->string('role_or_name');
            $table->string('contract_type_pattern')->nullable();
            $table->timestamps();
            $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
            $table->index('entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_authority');
    }
};
