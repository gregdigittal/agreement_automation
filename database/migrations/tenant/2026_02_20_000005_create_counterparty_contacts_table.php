<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('counterparty_contacts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('counterparty_id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('role')->nullable();
            $table->boolean('is_signer')->default(false);
            $table->timestamps();
            $table->foreign('counterparty_id')->references('id')->on('counterparties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparty_contacts');
    }
};
