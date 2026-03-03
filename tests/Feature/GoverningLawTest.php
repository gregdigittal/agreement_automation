<?php

namespace Tests\Feature;

use App\Models\GoverningLaw;
use App\Models\Contract;
use App\Models\Region;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Counterparty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoverningLawTest extends TestCase
{
    use RefreshDatabase;

    public function test_governing_law_can_be_created(): void
    {
        $law = GoverningLaw::create([
            'name' => 'England and Wales',
            'legal_system' => 'Common Law',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('governing_laws', [
            'name' => 'England and Wales',
            'legal_system' => 'Common Law',
        ]);

        $this->assertTrue($law->is_active);
    }

    public function test_governing_law_name_is_unique(): void
    {
        GoverningLaw::create([
            'name' => 'DIFC',
            'legal_system' => 'Common Law',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        GoverningLaw::create([
            'name' => 'DIFC',
            'legal_system' => 'Common Law',
        ]);
    }

    public function test_contract_belongs_to_governing_law(): void
    {
        $law = GoverningLaw::create([
            'name' => 'New York',
            'legal_system' => 'Common Law',
        ]);

        $region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
        $entity = Entity::create(['name' => 'Test Entity', 'code' => 'TE', 'region_id' => $region->id]);
        $project = Project::create(['name' => 'Test Project', 'code' => 'TP', 'entity_id' => $entity->id]);
        $counterparty = Counterparty::create(['legal_name' => 'Test Counterparty']);

        $contract = Contract::create([
            'title' => 'Test Contract',
            'contract_type' => 'Commercial',
            'region_id' => $region->id,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'counterparty_id' => $counterparty->id,
            'governing_law_id' => $law->id,
        ]);

        $this->assertEquals($law->id, $contract->governing_law_id);
        $this->assertEquals('New York', $contract->governingLaw->name);
    }

    public function test_governing_law_is_nullable_on_contract(): void
    {
        $region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
        $entity = Entity::create(['name' => 'Test Entity', 'code' => 'TE', 'region_id' => $region->id]);
        $project = Project::create(['name' => 'Test Project', 'code' => 'TP', 'entity_id' => $entity->id]);
        $counterparty = Counterparty::create(['legal_name' => 'Test Counterparty']);

        $contract = Contract::create([
            'title' => 'Contract Without Law',
            'contract_type' => 'Commercial',
            'region_id' => $region->id,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'counterparty_id' => $counterparty->id,
        ]);

        $this->assertNull($contract->governing_law_id);
        $this->assertNull($contract->governingLaw);
    }
}
