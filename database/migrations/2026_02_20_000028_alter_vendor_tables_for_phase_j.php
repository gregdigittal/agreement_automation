<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            $table->string('filename')->nullable()->after('contract_id');
            $table->string('document_type', 50)->default('supporting')->after('filename');
            $table->uuid('uploaded_by_vendor_user_id')->nullable()->after('document_type');
            $table->foreign('uploaded_by_vendor_user_id')->references('id')->on('vendor_users')->nullOnDelete();
        });

        Schema::table('vendor_users', function (Blueprint $table) {
            $table->string('login_token', 64)->nullable()->after('name');
            $table->timestamp('login_token_expires_at')->nullable()->after('login_token');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_documents', function (Blueprint $table) {
            $table->dropForeign(['uploaded_by_vendor_user_id']);
            $table->dropColumn(['filename', 'document_type', 'uploaded_by_vendor_user_id']);
        });

        Schema::table('vendor_users', function (Blueprint $table) {
            $table->dropColumn(['login_token', 'login_token_expires_at']);
        });
    }
};
