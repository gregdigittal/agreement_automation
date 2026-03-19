<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_pack_items', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('kyc_pack_id', 36);
            $table->char('kyc_template_item_id', 36)->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('label', 500);
            $table->text('description')->nullable();
            $table->enum('field_type', [
                'file_upload', 'text', 'textarea', 'number',
                'date', 'yes_no', 'select', 'attestation',
            ]);
            $table->boolean('is_required')->default(true);
            $table->json('options')->nullable();
            $table->json('validation_rules')->nullable();
            $table->text('value')->nullable();
            $table->string('file_path', 500)->nullable();
            $table->char('attested_by', 36)->nullable();
            $table->timestamp('attested_at')->nullable();
            $table->enum('status', ['pending', 'completed', 'not_applicable'])->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->char('completed_by', 36)->nullable();
            $table->timestamps();

            $table->foreign('kyc_pack_id')->references('id')->on('kyc_packs')->cascadeOnDelete();
            $table->foreign('attested_by')->references('id')->on('users');
            $table->foreign('completed_by')->references('id')->on('users');
            $table->index(['kyc_pack_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_pack_items');
    }
};
