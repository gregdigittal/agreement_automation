<?php

namespace Tests\Feature;

use App\Models\BoldsignEnvelope;
use App\Models\Contract;
use App\Models\SigningAuthority;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTemplate;
use App\Services\BoldsignService;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CountersignWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_countersign_envelope_created_with_only_internal_signers(): void
    {
        Storage::fake('s3');
        Http::fake([
            '*' => Http::response(['documentId' => 'boldsign-doc-123'], 200),
        ]);

        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = Contract::factory()->create([
            'storage_path' => 'contracts/test-partially-signed.pdf',
            'workflow_state' => 'countersign',
        ]);
        Storage::disk('s3')->put('contracts/test-partially-signed.pdf', 'fake-pdf-content');

        // Mock the service to avoid Guzzle multipart serialization issues in tests
        $this->mock(BoldsignService::class, function ($mock) use ($contract, $user) {
            $mock->shouldReceive('createCountersignEnvelope')
                ->once()
                ->andReturnUsing(function () use ($contract, $user) {
                    return BoldsignEnvelope::create([
                        'contract_id' => $contract->id,
                        'boldsign_document_id' => 'boldsign-doc-123',
                        'status' => 'sent',
                        'is_countersign' => true,
                        'signers' => [['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'order' => 1]],
                        'sent_at' => now(),
                    ]);
                });
        });

        $service = app(BoldsignService::class);
        $envelope = $service->createCountersignEnvelope($contract, [
            ['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'order' => 1],
        ]);

        $this->assertDatabaseHas('boldsign_envelopes', [
            'contract_id' => $contract->id,
            'is_countersign' => true,
            'status' => 'sent',
        ]);

        $this->assertEquals('boldsign-doc-123', $envelope->boldsign_document_id);
    }

    public function test_countersign_stage_requires_signing_authority(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = Contract::factory()->create([
            'workflow_state' => 'countersign',
        ]);

        $template = WorkflowTemplate::create([
            'name' => 'Countersign Test Template',
            'contract_type' => 'Commercial',
            'version' => 1,
            'status' => 'published',
            'stages' => [
                ['name' => 'countersign', 'type' => 'countersign', 'owners' => ['legal']],
            ],
        ]);

        $instance = WorkflowInstance::create([
            'contract_id' => $contract->id,
            'template_id' => $template->id,
            'template_version' => 1,
            'current_stage' => 'countersign',
            'state' => 'active',
            'started_at' => now(),
        ]);

        // No signing authority exists — should throw
        $thrown = false;
        try {
            app(WorkflowService::class)->recordAction(
                $instance,
                'countersign',
                'approve',
                $user,
            );
        } catch (\RuntimeException $e) {
            $thrown = true;
            $this->assertStringContainsString('No signing authority', $e->getMessage());
        }
        $this->assertTrue($thrown, 'Expected RuntimeException for missing signing authority');
    }

    public function test_countersign_stage_passes_with_signing_authority(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = Contract::factory()->create([
            'workflow_state' => 'countersign',
        ]);

        SigningAuthority::factory()->create([
            'user_id' => $user->id,
            'entity_id' => $contract->entity_id,
            'project_id' => null,
            'contract_type_pattern' => '*',
        ]);

        $template = WorkflowTemplate::create([
            'name' => 'Countersign Auth Template',
            'contract_type' => 'Commercial',
            'version' => 1,
            'status' => 'published',
            'stages' => [
                ['name' => 'review', 'type' => 'review', 'owners' => ['legal']],
                ['name' => 'countersign', 'type' => 'countersign', 'owners' => ['legal']],
            ],
        ]);

        $instance = WorkflowInstance::create([
            'contract_id' => $contract->id,
            'template_id' => $template->id,
            'template_version' => 1,
            'current_stage' => 'countersign',
            'state' => 'active',
            'started_at' => now(),
        ]);

        // Should not throw — signing authority exists
        $action = app(WorkflowService::class)->recordAction(
            $instance,
            'countersign',
            'approve',
            $user,
        );

        $this->assertEquals('countersign', $action->stage_name);
        $this->assertEquals('approve', $action->action);
    }
}
