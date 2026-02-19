<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_log', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->timestamp('at')->useCurrent();
            $table->string('actor_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('action');
            $table->string('resource_type');
            $table->string('resource_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->index('at');
            $table->index(['resource_type', 'resource_id']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
