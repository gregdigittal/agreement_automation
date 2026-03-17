<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wiki_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->string('name');
            $table->string('category')->nullable();
            $table->uuid('region_id')->nullable();
            $table->integer('version')->default(1);
            $table->enum('status', ['draft', 'review', 'published', 'deprecated'])->default('draft');
            $table->string('storage_path')->nullable();
            $table->string('file_name')->nullable();
            $table->text('description')->nullable();
            $table->string('created_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wiki_contracts');
    }
};
