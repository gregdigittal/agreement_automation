<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->boolean('exchange_room_enabled')->default(false)->after('sharepoint_version');
            $table->boolean('sharepoint_enabled')->default(false)->after('exchange_room_enabled');
            $table->string('sharepoint_folder_id')->nullable()->after('sharepoint_enabled');
            $table->string('sharepoint_site_id')->nullable()->after('sharepoint_folder_id');
            $table->string('sharepoint_drive_id')->nullable()->after('sharepoint_site_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn([
                'exchange_room_enabled',
                'sharepoint_enabled',
                'sharepoint_folder_id',
                'sharepoint_site_id',
                'sharepoint_drive_id',
            ]);
        });
    }
};
