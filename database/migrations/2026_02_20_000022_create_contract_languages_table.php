<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_languages', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->string('language_code', 10);
            $table->boolean('is_primary')->default(false);
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->unique(['contract_id', 'language_code']);
            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_languages');
    }
};
