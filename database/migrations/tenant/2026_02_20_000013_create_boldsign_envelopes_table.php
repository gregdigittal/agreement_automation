<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Expression;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('boldsign_envelopes', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->string('boldsign_document_id')->unique()->nullable();
            $table->enum('status', ['draft','sent','viewed','partially_signed','completed','declined','expired','voided'])->default('draft');
            $table->enum('signing_order', ['parallel', 'sequential'])->default('sequential');
            $table->json('signers')->default(new Expression("('[]')"));
            $table->json('webhook_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->index('contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boldsign_envelopes');
    }
};
