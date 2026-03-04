<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Add 'discovery' to the analysis_type enum in ai_analysis_results table
        DB::statement("ALTER TABLE ai_analysis_results MODIFY COLUMN analysis_type ENUM('summary', 'extraction', 'risk', 'deviation', 'obligations', 'discovery')");
    }

    public function down(): void
    {
        // Remove 'discovery' from the enum (will fail if any rows have 'discovery' type)
        DB::statement("ALTER TABLE ai_analysis_results MODIFY COLUMN analysis_type ENUM('summary', 'extraction', 'risk', 'deviation', 'obligations')");
    }
};
