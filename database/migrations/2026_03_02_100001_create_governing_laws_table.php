<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('governing_laws', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->string('name', 255)->unique();
            $table->char('country_code', 2)->nullable();
            $table->string('legal_system', 100)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            if (DB::connection()->getDriverName() !== 'sqlite') {
                $table->foreign('country_code')->references('code')->on('countries')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governing_laws');
    }
};
