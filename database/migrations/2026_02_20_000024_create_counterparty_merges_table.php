<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('counterparty_merges', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('source_counterparty_id');
            $table->uuid('target_counterparty_id');
            $table->string('merged_by');
            $table->string('merged_by_email')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('source_counterparty_id');
            $table->foreign('target_counterparty_id')->references('id')->on('counterparties');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counterparty_merges');
    }
};
