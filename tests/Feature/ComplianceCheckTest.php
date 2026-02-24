<?php

namespace Tests\Feature;

use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\RegulatoryFramework;
use App\Models\User;
use App\Services\RegulatoryComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComplianceCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.regulatory_compliance' => true]);
    }

    public function test_compliance_check_dispatches_job(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->actingAs($user);

        $contract = Contract::factory()->create([
            'workflow_state' => 'review',
        ]);

        $framework = RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'EU',
            'framework_name' => 'Test GDPR',
            'requirements' => [
                ['id' => 'test-1', 'text' => 'Must include DPA', 'category' => 'data_protection', 'severity' => 'critical'],
            ],
        ]);

        $service = app(RegulatoryComplianceService::class);
        $service->runComplianceCheck($contract, $framework);

        Queue::assertPushed(\App\Jobs\ProcessComplianceCheck::class);
    }

    public function test_compliance_check_blocked_when_feature_disabled(): void
    {
        config(['features.regulatory_compliance' => false]);

        $contract = Contract::factory()->create();

        $service = app(RegulatoryComplianceService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not enabled/');

        $service->runComplianceCheck($contract);
    }

    public function test_review_finding_updates_status(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $finding = ComplianceFinding::factory()->create([
            'status' => 'unclear',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $service = app(RegulatoryComplianceService::class);
        $updated = $service->reviewFinding($finding, 'compliant', $user);

        $this->assertEquals('compliant', $updated->status);
        $this->assertEquals($user->id, $updated->reviewed_by);
        $this->assertNotNull($updated->reviewed_at);
    }

    public function test_get_findings_grouped_by_framework(): void
    {
        $contract = Contract::factory()->create();
        $fw1 = RegulatoryFramework::factory()->create();
        $fw2 = RegulatoryFramework::factory()->create();

        ComplianceFinding::factory()->count(3)->create([
            'contract_id' => $contract->id,
            'framework_id' => $fw1->id,
        ]);
        ComplianceFinding::factory()->count(2)->create([
            'contract_id' => $contract->id,
            'framework_id' => $fw2->id,
        ]);

        $service = app(RegulatoryComplianceService::class);
        $findings = $service->getFindings($contract);

        $this->assertCount(2, $findings); // 2 groups
        $this->assertCount(3, $findings[$fw1->id]);
        $this->assertCount(2, $findings[$fw2->id]);
    }

    public function test_score_summary_calculates_correctly(): void
    {
        $contract = Contract::factory()->create();
        $fw = RegulatoryFramework::factory()->create();

        ComplianceFinding::factory()->count(3)->create([
            'contract_id' => $contract->id,
            'framework_id' => $fw->id,
            'status' => 'compliant',
        ]);
        ComplianceFinding::factory()->count(1)->create([
            'contract_id' => $contract->id,
            'framework_id' => $fw->id,
            'status' => 'non_compliant',
        ]);
        ComplianceFinding::factory()->count(1)->create([
            'contract_id' => $contract->id,
            'framework_id' => $fw->id,
            'status' => 'not_applicable',
        ]);

        $service = app(RegulatoryComplianceService::class);
        $scores = $service->getScoreSummary($contract);

        $this->assertArrayHasKey($fw->id, $scores->toArray());
        $score = $scores[$fw->id];
        $this->assertEquals(5, $score['total']);
        $this->assertEquals(3, $score['compliant']);
        $this->assertEquals(1, $score['non_compliant']);
        $this->assertEquals(1, $score['not_applicable']);
        // Score = 3 / (5 - 1) * 100 = 75.0
        $this->assertEquals(75.0, $score['score']);
    }

    public function test_review_finding_rejects_invalid_status(): void
    {
        $user = User::factory()->create();
        $finding = ComplianceFinding::factory()->create(['status' => 'unclear']);

        $this->expectException(\InvalidArgumentException::class);

        app(RegulatoryComplianceService::class)->reviewFinding($finding, 'invalid_status', $user);
    }
}
