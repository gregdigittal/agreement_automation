<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_users', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('counterparty_id');
            $table->string('email')->unique();
            $table->string('name')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->foreign('counterparty_id')->references('id')->on('counterparties')->cascadeOnDelete();
            $table->index('counterparty_id');
        });

        Schema::create('vendor_login_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('vendor_user_id');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('vendor_user_id')->references('id')->on('vendor_users')->cascadeOnDelete();
        });

        Schema::create('vendor_documents', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('counterparty_id');
            $table->uuid('contract_id')->nullable();
            $table->string('title');
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
            $table->foreign('counterparty_id')->references('id')->on('counterparties')->cascadeOnDelete();
            $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
            $table->index('counterparty_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_documents');
        Schema::dropIfExists('vendor_login_tokens');
        Schema::dropIfExists('vendor_users');
    }
};
