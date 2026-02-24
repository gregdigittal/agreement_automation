<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signing_session_signers', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('signing_session_id', 36);
            $table->string('signer_name', 255);
            $table->string('signer_email', 255);
            $table->enum('signer_type', ['internal', 'external'])->default('external');
            $table->integer('signing_order')->default(0);
            $table->string('token', 255)->unique()->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->enum('status', ['pending', 'sent', 'viewed', 'signed', 'declined'])->default('pending');
            $table->string('signature_image_path', 500)->nullable();
            $table->enum('signature_method', ['draw', 'type', 'upload'])->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();

            $table->foreign('signing_session_id')->references('id')->on('signing_sessions')->cascadeOnDelete();
            $table->index('signing_session_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_session_signers');
    }
};
