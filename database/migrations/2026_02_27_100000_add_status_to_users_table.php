<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('name');
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT chk_user_status CHECK (status IN ('active', 'pending', 'suspended'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT chk_user_status");
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
