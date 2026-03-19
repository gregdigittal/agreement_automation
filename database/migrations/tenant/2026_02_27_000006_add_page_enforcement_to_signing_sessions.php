<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('signing_sessions')) {
            return;
        }

        $addRequireAllPagesViewed = !Schema::hasColumn('signing_sessions', 'require_all_pages_viewed');
        $addRequirePageInitials = !Schema::hasColumn('signing_sessions', 'require_page_initials');

        if ($addRequireAllPagesViewed || $addRequirePageInitials) {
            Schema::table('signing_sessions', function (Blueprint $table) use ($addRequireAllPagesViewed, $addRequirePageInitials) {
                if ($addRequireAllPagesViewed) {
                    $table->boolean('require_all_pages_viewed')->default(false)->after('expires_at');
                }
                if ($addRequirePageInitials) {
                    $table->boolean('require_page_initials')->default(false)->after('require_all_pages_viewed');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('signing_sessions') && Schema::hasColumn('signing_sessions', 'require_page_initials')) {
            Schema::table('signing_sessions', function (Blueprint $table) {
                $table->dropColumn('require_page_initials');
            });
        }

        if (Schema::hasTable('signing_sessions') && Schema::hasColumn('signing_sessions', 'require_all_pages_viewed')) {
            Schema::table('signing_sessions', function (Blueprint $table) {
                $table->dropColumn('require_all_pages_viewed');
            });
        }
    }
};
