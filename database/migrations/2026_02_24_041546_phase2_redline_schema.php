<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 2 migration — commented out until Phase 2 begins.
     */
    public function up(): void
    {
        // Schema::create('redline_sessions', function (Blueprint $table) {
        //     $table->char('id', 36)->primary();
        //     $table->char('contract_id', 36)->index();
        //     $table->char('wiki_contract_id', 36)->nullable();
        //     $table->string('status', 20)->default('pending');
        //     $table->char('created_by', 36)->nullable();
        //     $table->timestamps();
        // });
        //
        // Schema::create('redline_clauses', function (Blueprint $table) {
        //     $table->char('id', 36)->primary();
        //     $table->char('session_id', 36)->index();
        //     $table->unsignedSmallInteger('clause_number');
        //     $table->longText('original_text');
        //     $table->longText('suggested_text')->nullable();
        //     $table->string('change_type', 20);
        //     $table->text('ai_rationale')->nullable();
        //     $table->string('status', 20)->default('pending');
        //     $table->char('reviewed_by', 36)->nullable();
        //     $table->timestamps();
        // });
    }

    public function down(): void
    {
        //
    }
};
