<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jurisdictions', function (Blueprint $table) {
            $table->string('arbitration_body', 255)->nullable()->after('regulatory_body');
            $table->string('arbitration_rules', 255)->nullable()->after('arbitration_body');
        });

        // Seed defaults for common jurisdictions by country code
        $defaults = [
            'AE' => ['arbitration_body' => 'DIAC', 'arbitration_rules' => 'DIAC Rules 2022'],
            'GB' => ['arbitration_body' => 'LCIA', 'arbitration_rules' => 'LCIA Rules 2020'],
            'US' => ['arbitration_body' => 'AAA / ICDR', 'arbitration_rules' => 'ICDR International Rules'],
            'SG' => ['arbitration_body' => 'SIAC', 'arbitration_rules' => 'SIAC Rules 2016'],
            'HK' => ['arbitration_body' => 'HKIAC', 'arbitration_rules' => 'HKIAC Rules 2018'],
            'FR' => ['arbitration_body' => 'ICC', 'arbitration_rules' => 'ICC Rules 2021'],
        ];

        foreach ($defaults as $code => $values) {
            DB::table('jurisdictions')
                ->where('country_code', $code)
                ->whereNull('arbitration_body')
                ->update($values);
        }
    }

    public function down(): void
    {
        Schema::table('jurisdictions', function (Blueprint $table) {
            $table->dropColumn(['arbitration_body', 'arbitration_rules']);
        });
    }
};
