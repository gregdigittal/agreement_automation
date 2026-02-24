<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signing_fields', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('signing_session_id', 36);
            $table->char('assigned_to_signer_id', 36);
            $table->enum('field_type', ['signature', 'initials', 'text', 'date', 'checkbox', 'dropdown']);
            $table->string('label', 255)->nullable();
            $table->integer('page_number');
            $table->decimal('x_position', 8, 2);
            $table->decimal('y_position', 8, 2);
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->boolean('is_required')->default(true);
            $table->json('options')->nullable();
            $table->text('value')->nullable();
            $table->timestamp('filled_at')->nullable();
            $table->timestamps();

            $table->foreign('signing_session_id')->references('id')->on('signing_sessions')->cascadeOnDelete();
            $table->foreign('assigned_to_signer_id')->references('id')->on('signing_session_signers');
            $table->index('signing_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signing_fields');
    }
};
