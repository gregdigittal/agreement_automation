<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->char('governing_law_id', 36)->nullable()->after('counterparty_id');
            $table->foreign('governing_law_id')
                ->references('id')->on('governing_laws')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['governing_law_id']);
            $table->dropColumn('governing_law_id');
        });
    }
};
