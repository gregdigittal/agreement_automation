<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('contract_id');
            $table->uuid('key_date_id')->nullable();
            $table->enum('reminder_type', ['expiry', 'renewal_notice', 'payment', 'sla', 'obligation', 'custom']);
            $table->integer('lead_days');
            $table->enum('channel', ['email', 'teams', 'calendar'])->default('email');
            $table->string('recipient_email')->nullable();
            $table->string('recipient_user_id')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('key_date_id')->references('id')->on('contract_key_dates')->cascadeOnDelete();
            $table->index('contract_id');
            $table->index(['next_due_at', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
