<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisdictions', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->string('name', 255);
            $table->char('country_code', 2);
            $table->string('regulatory_body', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
            $table->index('country_code');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisdictions');
    }
};
