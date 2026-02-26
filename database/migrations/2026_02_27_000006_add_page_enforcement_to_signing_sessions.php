<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signing_sessions', function (Blueprint $table) {
            $table->boolean('require_all_pages_viewed')->default(false)->after('expires_at');
            $table->boolean('require_page_initials')->default(false)->after('require_all_pages_viewed');
        });
    }

    public function down(): void
    {
        Schema::table('signing_sessions', function (Blueprint $table) {
            $table->dropColumn(['require_all_pages_viewed', 'require_page_initials']);
        });
    }
};
