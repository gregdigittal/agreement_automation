<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signing_sessions', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('contract_id', 36);
            $table->char('initiated_by', 36);
            $table->enum('signing_order', ['sequential', 'parallel'])->default('sequential');
            $table->enum('status', ['draft', 'active', 'completed', 'cancelled', 'expired'])->default('draft');
            $table->string('document_hash', 64)->nullable();
            $table->string('final_document_hash', 64)->nullable();
            $table->string('final_storage_path', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('initiated_by')->references('id')->on('users');
            $table->index('contract_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_sessions');
    }
};
