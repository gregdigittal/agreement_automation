<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_uploads', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('created_by', 36)->nullable();
            $table->string('csv_filename');
            $table->string('zip_filename')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('completed_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->string('status', 20)->default('processing');
            $table->timestamps();
        });

        Schema::create('bulk_upload_rows', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('bulk_upload_id', 36)->index();
            $table->unsignedSmallInteger('row_number');
            $table->json('row_data');
            $table->string('status', 20)->default('pending');
            $table->char('contract_id', 36)->nullable();
            $table->string('created_by', 36)->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->foreign('bulk_upload_id')->references('id')->on('bulk_uploads')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_upload_rows');
        Schema::dropIfExists('bulk_uploads');
    }
};
