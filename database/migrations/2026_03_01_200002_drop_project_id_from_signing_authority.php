<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Only run on MySQL — SQLite (test env) can't drop columns with FK definitions,
        // and the column is harmless in tests since the app uses the pivot relationship.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('signing_authority', 'project_id')) {
            Schema::table('signing_authority', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->dropColumn('project_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('signing_authority', function (Blueprint $table) {
            $table->uuid('project_id')->nullable()->after('entity_id');
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }
};
