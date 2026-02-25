<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signing_audit_log', function (Blueprint $table) {
            $table->foreign('signer_id')->references('id')->on('signing_session_signers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('signing_audit_log', function (Blueprint $table) {
            $table->dropForeign(['signer_id']);
        });
    }
};
