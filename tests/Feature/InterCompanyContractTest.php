<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterCompanyContractTest extends TestCase
{
    use RefreshDatabase;

    private function createBaseRecords(): array
    {
        $region = Region::create(['name' => 'MENA', 'code' => 'AE']);
        $entity1 = Entity::create(['name' => 'Digittal UAE', 'code' => 'DGT-AE', 'region_id' => $region->id]);
        $entity2 = Entity::create(['name' => 'Digittal UK', 'code' => 'DGT-UK', 'region_id' => $region->id]);
        $project = Project::create(['name' => 'Test Project', 'code' => 'TP', 'entity_id' => $entity1->id]);

        return compact('region', 'entity1', 'entity2', 'project');
    }

    public function test_intercompany_contract_can_be_created_without_counterparty(): void
    {
        $base = $this->createBaseRecords();

        $contract = Contract::create([
            'title' => 'Inter-Company Services Agreement',
            'contract_type' => 'Inter-Company',
            'region_id' => $base['region']->id,
            'entity_id' => $base['entity1']->id,
            'second_entity_id' => $base['entity2']->id,
            'project_id' => $base['project']->id,
        ]);

        $this->assertDatabaseHas('contracts', [
            'id' => $contract->id,
            'contract_type' => 'Inter-Company',
        ]);

        $this->assertNull($contract->counterparty_id);
        $this->assertNull($contract->counterparty);
        $this->assertEquals($base['entity2']->id, $contract->second_entity_id);
    }

    public function test_second_entity_relationship_works(): void
    {
        $base = $this->createBaseRecords();

        $contract = Contract::create([
            'title' => 'Intra-Group Loan Agreement',
            'contract_type' => 'Inter-Company',
            'region_id' => $base['region']->id,
            'entity_id' => $base['entity1']->id,
            'second_entity_id' => $base['entity2']->id,
            'project_id' => $base['project']->id,
        ]);

        $this->assertEquals('Digittal UK', $contract->secondEntity->name);
        $this->assertEquals('Digittal UAE', $contract->entity->name);
    }

    public function test_commercial_contract_still_requires_counterparty(): void
    {
        $base = $this->createBaseRecords();
        $counterparty = Counterparty::create(['legal_name' => 'Acme Corp']);

        $contract = Contract::create([
            'title' => 'Standard Commercial Agreement',
            'contract_type' => 'Commercial',
            'region_id' => $base['region']->id,
            'entity_id' => $base['entity1']->id,
            'project_id' => $base['project']->id,
            'counterparty_id' => $counterparty->id,
        ]);

        $this->assertEquals($counterparty->id, $contract->counterparty_id);
        $this->assertNull($contract->second_entity_id);
    }

    public function test_second_entity_is_nullable(): void
    {
        $base = $this->createBaseRecords();
        $counterparty = Counterparty::create(['legal_name' => 'Test Corp']);

        $contract = Contract::create([
            'title' => 'Regular Contract',
            'contract_type' => 'Commercial',
            'region_id' => $base['region']->id,
            'entity_id' => $base['entity1']->id,
            'project_id' => $base['project']->id,
            'counterparty_id' => $counterparty->id,
        ]);

        $this->assertNull($contract->second_entity_id);
        $this->assertNull($contract->secondEntity);
    }
}
