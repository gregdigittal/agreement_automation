<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('counterparties', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('legal_name');
            $table->string('registration_number')->nullable();
            $table->text('address')->nullable();
            $table->string('jurisdiction')->nullable();
            $table->enum('status', ['Active', 'Suspended', 'Blacklisted'])->default('Active');
            $table->text('status_reason')->nullable();
            $table->timestamp('status_changed_at')->nullable();
            $table->string('status_changed_by')->nullable();
            $table->string('preferred_language', 10)->default('en');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparties');
    }
};
