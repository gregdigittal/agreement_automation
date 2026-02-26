<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_signing_fields', function (Blueprint $table) {
            $table->char('id', 36)->primary()->default(DB::raw('(UUID())'));
            $table->char('wiki_contract_id', 36);
            $table->string('field_type', 20); // signature, initials, text, date
            $table->string('signer_role', 50); // company, counterparty, witness_1, etc.
            $table->string('label', 255)->nullable();
            $table->integer('page_number');
            $table->decimal('x_position', 8, 2);
            $table->decimal('y_position', 8, 2);
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->boolean('is_required')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('wiki_contract_id')->references('id')->on('wiki_contracts')->cascadeOnDelete();
            $table->index('wiki_contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_signing_fields');
    }
};
