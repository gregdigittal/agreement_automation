<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contracts') && !Schema::hasColumn('contracts', 'is_restricted')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->boolean('is_restricted')->default(false)->after('signing_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts') && Schema::hasColumn('contracts', 'is_restricted')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->dropColumn('is_restricted');
            });
        }
    }
};
