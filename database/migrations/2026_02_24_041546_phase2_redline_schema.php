<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redline_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->uuid('wiki_contract_id')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('created_by')->nullable();
            $table->integer('total_clauses')->default(0);
            $table->integer('reviewed_clauses')->default(0);
            $table->json('summary')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('wiki_contract_id')->references('id')->on('wiki_contracts')->nullOnDelete();
            $table->index('contract_id');
            $table->index('status');
        });

        Schema::create('redline_clauses', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('session_id');
            $table->unsignedSmallInteger('clause_number');
            $table->string('clause_heading')->nullable();
            $table->longText('original_text');
            $table->longText('suggested_text')->nullable();
            $table->enum('change_type', ['unchanged', 'addition', 'deletion', 'modification']);
            $table->text('ai_rationale')->nullable();
            $table->double('confidence')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected', 'modified'])->default('pending');
            $table->longText('final_text')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('id')->on('redline_sessions')->cascadeOnDelete();
            $table->index('session_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redline_clauses');
        Schema::dropIfExists('redline_sessions');
    }
};
