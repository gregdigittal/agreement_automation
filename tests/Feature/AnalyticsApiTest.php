<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['features.advanced_analytics' => true]);
    }

    public function test_pipeline_endpoint_returns_data(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        Contract::factory()->count(3)->create(['workflow_state' => 'draft']);
        Contract::factory()->count(2)->create(['workflow_state' => 'executed']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/pipeline');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_analytics_blocked_when_feature_disabled(): void
    {
        config(['features.advanced_analytics' => false]);

        $user = User::factory()->create();
        $user->assignRole('system_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/pipeline');

        $response->assertNotFound();
    }

    public function test_ai_costs_endpoint_returns_summary(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/ai-costs');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['daily', 'summary']]);
    }

    public function test_obligations_timeline_accepts_filters(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/obligations-timeline?status=pending');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_risk_distribution_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/risk-distribution');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_workflow_performance_endpoint(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/workflow-performance');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
