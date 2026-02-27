<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('file_storage')) {
            Schema::create('file_storage', function (Blueprint $table) {
                $table->id();
                $table->string('path', 1024)->unique();
                $table->binary('contents');
                $table->string('mime_type', 255)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->string('visibility', 10)->default('private');
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // Alter contents column to LONGBLOB on MySQL (Laravel's binary() creates BLOB which is 64KB max)
        // SQLite has no BLOB size limit, so this is only needed for MySQL
        if (Schema::hasTable('file_storage') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE file_storage MODIFY contents LONGBLOB');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('file_storage');
    }
};
