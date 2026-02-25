<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->string('legal_name', 500)->nullable()->after('name');
            $table->string('registration_number', 100)->nullable()->after('legal_name');
            $table->text('registered_address')->nullable()->after('registration_number');
            $table->char('parent_entity_id', 36)->nullable()->after('registered_address');

            $table->foreign('parent_entity_id')->references('id')->on('entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entities', function (Blueprint $table) {
            $table->dropForeign(['parent_entity_id']);
            $table->dropColumn(['legal_name', 'registration_number', 'registered_address', 'parent_entity_id']);
        });
    }
};
