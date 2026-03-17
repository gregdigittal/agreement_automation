<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE counterparties MODIFY COLUMN status ENUM('Active', 'Suspended', 'Blacklisted', 'Merged') DEFAULT 'Active'");
        } elseif ($driver === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN; recreate table with updated CHECK
            DB::statement('PRAGMA foreign_keys = OFF');

            $tableInfo = DB::selectOne("SELECT sql FROM sqlite_master WHERE type='table' AND name='counterparties'");
            if ($tableInfo) {
                DB::statement('CREATE TABLE _counterparties_backup AS SELECT * FROM counterparties');
                DB::statement('DROP TABLE counterparties');

                $newSql = str_replace(
                    "('Active', 'Suspended', 'Blacklisted')",
                    "('Active', 'Suspended', 'Blacklisted', 'Merged')",
                    $tableInfo->sql
                );
                DB::statement($newSql);

                DB::statement('INSERT INTO counterparties SELECT * FROM _counterparties_backup');
                DB::statement('DROP TABLE _counterparties_backup');
            }

            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE counterparties MODIFY COLUMN status ENUM('Active', 'Suspended', 'Blacklisted') DEFAULT 'Active'");
        }
    }
};
