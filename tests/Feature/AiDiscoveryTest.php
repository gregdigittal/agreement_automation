<?php

namespace Tests\Feature;

use App\Models\AiDiscoveryDraft;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Services\AiDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function makeContract(): Contract
    {
        $region = Region::firstOrCreate(['code' => 'AE'], ['name' => 'UAE']);
        $entity = Entity::firstOrCreate(['code' => 'DGT-AE'], ['name' => 'Digittal FZ-LLC', 'region_id' => $region->id]);
        $project = Project::create(['name' => 'Test Project', 'code' => 'TP-' . uniqid(), 'entity_id' => $entity->id]);
        $cp = Counterparty::firstOrCreate(['registration_number' => 'REG-123'], ['legal_name' => 'Acme Corp', 'status' => 'Active']);
        $contract = new Contract([
            'title' => 'Test Contract',
            'contract_type' => 'Commercial',
            'region_id' => $region->id,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'counterparty_id' => $cp->id,
        ]);
        $contract->workflow_state = 'draft';
        $contract->save();
        return $contract;
    }

    public function test_discovery_drafts_are_created(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.9,
                'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
            ],
            [
                'type' => 'governing_law',
                'confidence' => 0.7,
                'data' => ['name' => 'Laws of England and Wales'],
            ],
        ]);

        $this->assertDatabaseCount('ai_discovery_drafts', 2);
    }

    public function test_counterparty_is_matched_by_registration(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.95,
                'data' => ['legal_name' => 'Acme Corporation', 'registration_number' => 'REG-123'],
            ],
        ]);

        $draft = AiDiscoveryDraft::first();
        $this->assertNotNull($draft->matched_record_id);
        $this->assertEquals(Counterparty::class, $draft->matched_record_type);
    }

    public function test_approve_creates_new_counterparty_when_no_match(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.8,
                'data' => ['legal_name' => 'Brand New Corp', 'registration_number' => 'NEW-999'],
            ],
        ]);

        $draft = AiDiscoveryDraft::first();
        $this->assertNull($draft->matched_record_id);

        $admin = \App\Models\User::factory()->create();
        $service->approveDraft($draft, $admin);

        $this->assertEquals('approved', $draft->fresh()->status);
        $this->assertDatabaseHas('counterparties', ['legal_name' => 'Brand New Corp']);
    }

    public function test_reject_does_not_create_record(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.3,
                'data' => ['legal_name' => 'Suspicious Corp'],
            ],
        ]);

        $draft = AiDiscoveryDraft::first();
        $admin = \App\Models\User::factory()->create();
        $service->rejectDraft($draft, $admin);

        $this->assertEquals('rejected', $draft->fresh()->status);
        $this->assertDatabaseMissing('counterparties', ['legal_name' => 'Suspicious Corp']);
    }

    public function test_duplicate_counterparty_discovery_is_not_created(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        // First discovery run
        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.9,
                'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
            ],
        ]);

        $this->assertDatabaseCount('ai_discovery_drafts', 1);

        // Second discovery run with identical counterparty
        $service->processDiscoveryResults($contract, 'analysis-2', [
            [
                'type' => 'counterparty',
                'confidence' => 0.95,
                'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
            ],
        ]);

        // Should still be 1 — duplicate was skipped
        $this->assertDatabaseCount('ai_discovery_drafts', 1);
    }

    public function test_different_counterparty_discovery_is_created(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.9,
                'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
            ],
        ]);

        // Different counterparty
        $service->processDiscoveryResults($contract, 'analysis-2', [
            [
                'type' => 'counterparty',
                'confidence' => 0.85,
                'data' => ['legal_name' => 'Beta LLC', 'registration_number' => 'REG-999'],
            ],
        ]);

        $this->assertDatabaseCount('ai_discovery_drafts', 2);
    }

    public function test_duplicate_jurisdiction_discovery_is_not_created(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        $service->processDiscoveryResults($contract, 'analysis-1', [
            ['type' => 'jurisdiction', 'confidence' => 0.8, 'data' => ['name' => 'DIFC']],
        ]);

        $service->processDiscoveryResults($contract, 'analysis-2', [
            ['type' => 'jurisdiction', 'confidence' => 0.9, 'data' => ['name' => 'DIFC']],
        ]);

        $this->assertDatabaseCount('ai_discovery_drafts', 1);
    }

    public function test_approved_draft_does_not_block_new_discovery(): void
    {
        $contract = $this->makeContract();
        $service = new AiDiscoveryService();

        // First run
        $service->processDiscoveryResults($contract, 'analysis-1', [
            [
                'type' => 'counterparty',
                'confidence' => 0.9,
                'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
            ],
        ]);

        // Approve the draft
        $draft = AiDiscoveryDraft::first();
        $admin = \App\Models\User::factory()->create();
        $service->approveDraft($draft, $admin);

        // Second run with same data — should create new draft since first is approved
        $service->processDiscoveryResults($contract, 'analysis-2', [
            [
                'type' => 'counterparty',
                'confidence' => 0.95,
                'data' => ['legal_name' => 'Acme Corp', 'registration_number' => 'REG-123'],
            ],
        ]);

        $this->assertDatabaseCount('ai_discovery_drafts', 2);
    }

    public function test_duplicate_across_different_contracts_is_allowed(): void
    {
        $contract1 = $this->makeContract();
        $contract2 = $this->makeContract();
        $service = new AiDiscoveryService();

        $discovery = [
            ['type' => 'counterparty', 'confidence' => 0.9, 'data' => ['legal_name' => 'Acme Corp']],
        ];

        $service->processDiscoveryResults($contract1, 'analysis-1', $discovery);
        $service->processDiscoveryResults($contract2, 'analysis-2', $discovery);

        // Different contracts — both should be created
        $this->assertDatabaseCount('ai_discovery_drafts', 2);
    }
}
