<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stored_signatures', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('user_id', 36)->nullable();
            $table->char('counterparty_id', 36)->nullable();
            $table->string('signer_email', 255)->nullable();
            $table->string('label', 100)->nullable();
            $table->string('type', 20)->default('signature'); // signature or initials
            $table->string('capture_method', 20)->default('draw'); // draw, type, upload, webcam
            $table->string('image_path', 500);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('counterparty_id')->references('id')->on('counterparties')->cascadeOnDelete();
            $table->index('user_id');
            $table->index('counterparty_id');
            $table->index('signer_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stored_signatures');
    }
};
