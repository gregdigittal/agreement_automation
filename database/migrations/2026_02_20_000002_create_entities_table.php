<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('region_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
            $table->foreign('region_id')->references('id')->on('regions')->restrictOnDelete();
            $table->unique(['region_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entities');
    }
};
