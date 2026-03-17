<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_agreements', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('counterparty_id');
            $table->uuid('region_id');
            $table->uuid('entity_id');
            $table->uuid('project_id');
            $table->decimal('merchant_fee', 10, 2)->nullable();
            $table->text('region_terms')->nullable();
            $table->timestamps();
            $table->foreign('counterparty_id')->references('id')->on('counterparties')->cascadeOnDelete();
            $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
            $table->foreign('entity_id')->references('id')->on('entities')->cascadeOnDelete();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_agreements');
    }
};
