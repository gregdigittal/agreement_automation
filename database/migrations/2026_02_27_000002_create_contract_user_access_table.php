<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_user_access', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('contract_id', 36);
            $table->char('user_id', 36);
            $table->string('access_level', 20)->default('view');
            $table->char('granted_by', 36)->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'user_id']);

            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('granted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_user_access');
    }
};
