<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signing_session_signers', function (Blueprint $table) {
            // SQLite doesn't support ENUM modification, so we change to string for flexibility
            $table->string('signature_method', 20)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('signing_session_signers', function (Blueprint $table) {
            $table->string('signature_method', 20)->nullable()->change();
        });
    }
};
