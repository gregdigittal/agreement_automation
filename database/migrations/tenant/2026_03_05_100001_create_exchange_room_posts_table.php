<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_room_posts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->char('room_id', 36);
            $table->string('author_type');
            $table->char('author_id', 36);
            $table->string('author_name');
            $table->enum('actor_side', ['internal', 'vendor']);
            $table->text('message')->nullable();
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('version_number')->nullable();
            $table->timestamps();

            $table->foreign('room_id')->references('id')->on('exchange_rooms')->cascadeOnDelete();
            $table->index('room_id');
            $table->index(['author_type', 'author_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_room_posts');
    }
};
