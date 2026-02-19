<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contract_links', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('parent_contract_id');
            $table->uuid('child_contract_id');
            $table->enum('link_type', ['amendment', 'renewal', 'side_letter', 'addendum']);
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('parent_contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->foreign('child_contract_id')->references('id')->on('contracts')->cascadeOnDelete();
            $table->unique(['parent_contract_id', 'child_contract_id']);
            $table->index('parent_contract_id');
            $table->index('child_contract_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_links');
    }
};
