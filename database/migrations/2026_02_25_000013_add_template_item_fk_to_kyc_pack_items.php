<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        try {
            Schema::table('kyc_pack_items', function (Blueprint $table) {
                $table->foreign('kyc_template_item_id')->references('id')->on('kyc_template_items')->nullOnDelete();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // FK already exists — skip
        }
    }

    public function down(): void
    {
        Schema::table('kyc_pack_items', function (Blueprint $table) {
            $table->dropForeign(['kyc_template_item_id']);
        });
    }
};
