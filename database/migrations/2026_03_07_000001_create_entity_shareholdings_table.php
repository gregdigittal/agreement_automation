<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_shareholdings', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('owner_entity_id', 36);
            $table->char('owned_entity_id', 36);
            $table->decimal('percentage', 5, 2);
            $table->string('ownership_type', 20)->default('direct');
            $table->date('effective_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('owner_entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('owned_entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->unique(['owner_entity_id', 'owned_entity_id', 'ownership_type'], 'entity_shareholdings_unique');
            $table->index('owned_entity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_shareholdings');
    }
};
