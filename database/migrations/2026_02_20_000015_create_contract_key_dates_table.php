<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_key_dates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->string('date_type', 100);
            $table->date('date_value');
            $table->text('description')->nullable();
            $table->json('reminder_days')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->string('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->index('contract_id');
            $table->index('date_value');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_key_dates');
    }
};
