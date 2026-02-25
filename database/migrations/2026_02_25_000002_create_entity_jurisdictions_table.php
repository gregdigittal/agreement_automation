<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_jurisdictions', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('entity_id', 36);
            $table->char('jurisdiction_id', 36);
            $table->string('license_number', 100)->nullable();
            $table->date('license_expiry')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('jurisdiction_id')->references('id')->on('jurisdictions')->cascadeOnDelete();
            $table->unique(['entity_id', 'jurisdiction_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_jurisdictions');
    }
};
