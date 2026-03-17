<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('vendor_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('(UUID())'));
            $table->uuid('vendor_user_id');
            $table->string('subject');
            $table->text('body');
            $table->string('related_resource_type', 50)->nullable();
            $table->uuid('related_resource_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->foreign('vendor_user_id')->references('id')->on('vendor_users')->cascadeOnDelete();
            $table->index('vendor_user_id');
        });
    }
    public function down(): void { Schema::dropIfExists('vendor_notifications'); }
};
