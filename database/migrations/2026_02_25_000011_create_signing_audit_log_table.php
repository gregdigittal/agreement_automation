<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signing_audit_log', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('signing_session_id', 36);
            $table->char('signer_id', 36)->nullable();
            $table->enum('event', [
                'created', 'sent', 'viewed', 'field_filled', 'signed',
                'declined', 'cancelled', 'expired', 'completed', 'reminder_sent',
            ]);
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');

            $table->foreign('signing_session_id')->references('id')->on('signing_sessions')->cascadeOnDelete();
            $table->index(['signing_session_id', 'event']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_audit_log');
    }
};
