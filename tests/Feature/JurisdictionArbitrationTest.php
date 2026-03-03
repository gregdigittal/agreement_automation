<?php

namespace Tests\Feature;

use App\Models\Jurisdiction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JurisdictionArbitrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_jurisdiction_has_arbitration_fields(): void
    {
        $jurisdiction = Jurisdiction::create([
            'name' => 'UAE - DIFC',
            'country_code' => 'AE',
            'regulatory_body' => 'DIFC Authority',
            'arbitration_body' => 'DIAC',
            'arbitration_rules' => 'DIAC Rules 2022',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('jurisdictions', [
            'name' => 'UAE - DIFC',
            'arbitration_body' => 'DIAC',
            'arbitration_rules' => 'DIAC Rules 2022',
        ]);

        $fresh = Jurisdiction::find($jurisdiction->id);
        $this->assertEquals('DIAC', $fresh->arbitration_body);
        $this->assertEquals('DIAC Rules 2022', $fresh->arbitration_rules);
    }

    public function test_arbitration_fields_are_nullable(): void
    {
        $jurisdiction = Jurisdiction::create([
            'name' => 'Test Jurisdiction',
            'country_code' => 'XX',
            'is_active' => true,
        ]);

        $this->assertNull($jurisdiction->arbitration_body);
        $this->assertNull($jurisdiction->arbitration_rules);
    }
}
