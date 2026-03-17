<?php

namespace Tests\Feature\Compliance;

use App\Jobs\ProcessComplianceCheck;
use App\Models\Contract;
use App\Models\Entity;
use App\Models\Region;
use App\Models\RegulatoryFramework;
use App\Services\RegulatoryComplianceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ComplianceAutoDetectTest extends TestCase
{
    use RefreshDatabase;

    private RegulatoryComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.regulatory_compliance' => true]);
        $this->service = app(RegulatoryComplianceService::class);
    }

    public function test_auto_detects_jurisdiction_specific_framework(): void
    {
        Queue::fake();

        $region = Region::factory()->create(['code' => 'ZA']);
        $entity = Entity::factory()->create(['region_id' => $region->id]);
        $contract = Contract::factory()->create(['entity_id' => $entity->id]);

        $zaFramework = RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'ZA',
            'is_active' => true,
        ]);
        RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'US',
            'is_active' => true,
        ]);

        $this->service->runComplianceCheck($contract);

        Queue::assertPushed(ProcessComplianceCheck::class, 1);
        Queue::assertPushed(ProcessComplianceCheck::class, function ($job) use ($zaFramework) {
            return $job->framework->id === $zaFramework->id;
        });
    }

    public function test_auto_detects_global_framework_alongside_jurisdiction(): void
    {
        Queue::fake();

        $region = Region::factory()->create(['code' => 'ZA']);
        $entity = Entity::factory()->create(['region_id' => $region->id]);
        $contract = Contract::factory()->create(['entity_id' => $entity->id]);

        $zaFramework = RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'ZA',
            'is_active' => true,
        ]);
        $globalFramework = RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'GLOBAL',
            'is_active' => true,
        ]);

        $this->service->runComplianceCheck($contract);

        Queue::assertPushed(ProcessComplianceCheck::class, 2);
    }

    public function test_falls_back_to_global_when_no_jurisdiction(): void
    {
        Queue::fake();

        // Contract with entity whose region has no code — service falls back to GLOBAL
        $region = Region::factory()->create(['code' => null]);
        $entity = Entity::factory()->create(['region_id' => $region->id]);
        $contract = Contract::factory()->create(['entity_id' => $entity->id]);

        RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'ZA',
            'is_active' => true,
        ]);
        $globalFramework = RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'GLOBAL',
            'is_active' => true,
        ]);

        $this->service->runComplianceCheck($contract);

        Queue::assertPushed(ProcessComplianceCheck::class, 1);
        Queue::assertPushed(ProcessComplianceCheck::class, function ($job) use ($globalFramework) {
            return $job->framework->id === $globalFramework->id;
        });
    }

    public function test_dispatches_nothing_when_no_applicable_frameworks(): void
    {
        Queue::fake();

        $region = Region::factory()->create(['code' => 'ZA']);
        $entity = Entity::factory()->create(['region_id' => $region->id]);
        $contract = Contract::factory()->create(['entity_id' => $entity->id]);

        // Only inactive frameworks
        RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'ZA',
            'is_active' => false,
        ]);

        $this->service->runComplianceCheck($contract);

        Queue::assertNothingPushed();
    }

    public function test_dispatches_specific_framework_when_provided(): void
    {
        Queue::fake();

        $contract = Contract::factory()->create();
        $framework = RegulatoryFramework::factory()->create(['is_active' => true]);

        $this->service->runComplianceCheck($contract, $framework);

        Queue::assertPushed(ProcessComplianceCheck::class, 1);
        Queue::assertPushed(ProcessComplianceCheck::class, function ($job) use ($framework, $contract) {
            return $job->framework->id === $framework->id
                && $job->contract->id === $contract->id;
        });
    }

    public function test_falls_back_to_global_when_region_code_is_null(): void
    {
        Queue::fake();

        // Region exists but code is null — service falls back to GLOBAL-only frameworks
        $region = Region::factory()->create(['code' => null]);
        $entity = Entity::factory()->create(['region_id' => $region->id]);
        $contract = Contract::factory()->create(['entity_id' => $entity->id]);

        RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'ZA',
            'is_active' => true,
        ]);
        $globalFramework = RegulatoryFramework::factory()->create([
            'jurisdiction_code' => 'GLOBAL',
            'is_active' => true,
        ]);

        $this->service->runComplianceCheck($contract);

        Queue::assertPushed(ProcessComplianceCheck::class, 1);
        Queue::assertPushed(ProcessComplianceCheck::class, function ($job) use ($globalFramework) {
            return $job->framework->id === $globalFramework->id;
        });
    }
}
