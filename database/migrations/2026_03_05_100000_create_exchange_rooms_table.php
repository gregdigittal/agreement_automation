<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->char('contract_id', 36)->unique();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->enum('negotiation_stage', ['draft_round', 'vendor_review', 'revised', 'final'])->default('draft_round');
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rooms');
    }
};
