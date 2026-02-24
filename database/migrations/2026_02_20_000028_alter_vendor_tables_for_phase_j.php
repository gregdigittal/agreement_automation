<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // vendor_documents and vendor_users columns now in create_vendor_tables (Phase G)
        // No alterations needed.
    }

    public function down(): void
    {
        // No-op: columns are defined in create_vendor_tables
    }
};
