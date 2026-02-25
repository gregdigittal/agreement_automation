<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signing_session_signers', function (Blueprint $table) {
            // Only change column size; the unique index already exists from the original migration.
            $table->string('token', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('signing_session_signers', function (Blueprint $table) {
            $table->string('token', 255)->nullable()->change();
        });
    }
};
