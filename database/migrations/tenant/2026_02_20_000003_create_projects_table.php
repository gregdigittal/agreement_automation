<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('entity_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
            $table->foreign('entity_id')->references('id')->on('entities')->restrictOnDelete();
            $table->unique(['entity_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
