<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_template_items', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('kyc_template_id', 36);
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
            $table->timestamps();

            $table->foreign('kyc_template_id')->references('id')->on('kyc_templates')->cascadeOnDelete();
            $table->index(['kyc_template_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_template_items');
    }
};
