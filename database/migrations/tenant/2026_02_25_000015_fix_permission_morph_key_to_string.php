<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix Spatie permission pivot tables: change model morph key from
 * unsignedBigInteger to string so it can hold UUID user IDs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $morphKey = $columnNames['model_morph_key'];

        // Truncate first — any existing rows have invalid integer values
        // from the original unsignedBigInteger column.
        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($morphKey) {
            $table->string($morphKey)->change();
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($morphKey) {
            $table->string($morphKey)->change();
        });
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $morphKey = $columnNames['model_morph_key'];

        Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($morphKey) {
            $table->unsignedBigInteger($morphKey)->change();
        });

        Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($morphKey) {
            $table->unsignedBigInteger($morphKey)->change();
        });
    }
};
