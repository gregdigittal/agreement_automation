<?php

namespace Tests\Feature;

use App\Helpers\Feature;
use App\Jobs\ProcessRedlineAnalysis;
use App\Models\Contract;
use App\Models\RedlineClause;
use App\Models\RedlineSession;
use App\Models\User;
use App\Models\WikiContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RedlineTest extends TestCase
{
    use RefreshDatabase;

    private User $legalUser;
    private Contract $contract;
    private WikiContract $template;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.redlining' => true]);

        $this->legalUser = User::factory()->create();
        $this->legalUser->assignRole('legal');

        $this->contract = Contract::factory()->create([
            'storage_path' => 'contracts/test-contract.pdf',
            'workflow_state' => 'review',
        ]);

        $this->template = WikiContract::factory()->published()->create([
            'region_id' => $this->contract->region_id,
            'storage_path' => 'templates/test-template.docx',
        ]);
    }

    public function test_start_session_creates_record_and_dispatches_job(): void
    {
        Queue::fake();

        $this->actingAs($this->legalUser);

        $service = app(\App\Services\RedlineService::class);
        $session = $service->startSession($this->contract, $this->template, $this->legalUser);

        $this->assertDatabaseHas('redline_sessions', [
            'id' => $session->id,
            'contract_id' => $this->contract->id,
            'wiki_contract_id' => $this->template->id,
            'status' => 'pending',
            'created_by' => $this->legalUser->id,
        ]);

        Queue::assertPushed(ProcessRedlineAnalysis::class, function ($job) use ($session) {
            return $job->sessionId === $session->id;
        });
    }

    public function test_start_session_auto_selects_template_by_region(): void
    {
        Queue::fake();

        $this->actingAs($this->legalUser);

        $service = app(\App\Services\RedlineService::class);
        $session = $service->startSession($this->contract, null, $this->legalUser);

        $this->assertEquals($this->template->id, $session->wiki_contract_id);
    }

    public function test_start_session_throws_when_no_template_available(): void
    {
        Queue::fake();

        WikiContract::where('region_id', $this->contract->region_id)->delete();

        $this->actingAs($this->legalUser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/No published WikiContract template found/');

        app(\App\Services\RedlineService::class)->startSession(
            $this->contract,
            null,
            $this->legalUser,
        );
    }

    public function test_review_clause_accept_updates_status_and_progress(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'wiki_contract_id' => $this->template->id,
            'status' => 'completed',
            'total_clauses' => 3,
            'reviewed_clauses' => 0,
        ]);

        $clause = RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 1,
            'original_text' => 'Original clause text.',
            'suggested_text' => 'Suggested clause text from template.',
            'change_type' => 'modification',
            'status' => 'pending',
        ]);

        $service = app(\App\Services\RedlineService::class);
        $reviewed = $service->reviewClause($clause, 'accepted', null, $this->legalUser);

        $this->assertEquals('accepted', $reviewed->status);
        $this->assertEquals('Suggested clause text from template.', $reviewed->final_text);
        $this->assertEquals($this->legalUser->id, $reviewed->reviewed_by);
        $this->assertNotNull($reviewed->reviewed_at);

        $session->refresh();
        $this->assertEquals(1, $session->reviewed_clauses);
    }

    public function test_review_clause_reject_keeps_original_text(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
            'total_clauses' => 1,
        ]);

        $clause = RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 1,
            'original_text' => 'Keep this original text.',
            'suggested_text' => 'Different suggested text.',
            'change_type' => 'modification',
            'status' => 'pending',
        ]);

        $service = app(\App\Services\RedlineService::class);
        $reviewed = $service->reviewClause($clause, 'rejected', null, $this->legalUser);

        $this->assertEquals('rejected', $reviewed->status);
        $this->assertEquals('Keep this original text.', $reviewed->final_text);
    }

    public function test_review_clause_modify_uses_custom_text(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
            'total_clauses' => 1,
        ]);

        $clause = RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 1,
            'original_text' => 'Original text.',
            'suggested_text' => 'Suggested text.',
            'change_type' => 'modification',
            'status' => 'pending',
        ]);

        $customText = 'My custom modified clause text that combines elements of both.';

        $service = app(\App\Services\RedlineService::class);
        $reviewed = $service->reviewClause($clause, 'modified', $customText, $this->legalUser);

        $this->assertEquals('modified', $reviewed->status);
        $this->assertEquals($customText, $reviewed->final_text);
    }

    public function test_review_clause_modify_requires_final_text(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
            'total_clauses' => 1,
        ]);

        $clause = RedlineClause::factory()->create([
            'session_id' => $session->id,
            'status' => 'pending',
            'change_type' => 'modification',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(\App\Services\RedlineService::class)->reviewClause(
            $clause,
            'modified',
            null,
            $this->legalUser,
        );
    }

    public function test_generate_final_document_requires_all_clauses_reviewed(): void
    {
        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
            'total_clauses' => 2,
            'reviewed_clauses' => 1,
        ]);

        RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 1,
            'status' => 'accepted',
            'reviewed_at' => now(),
        ]);
        RedlineClause::factory()->create([
            'session_id' => $session->id,
            'clause_number' => 2,
            'status' => 'pending',
            'reviewed_at' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/clause\(s\) have not been reviewed/');

        app(\App\Services\RedlineService::class)->generateFinalDocument($session);
    }

    public function test_feature_gate_hides_redline_when_disabled(): void
    {
        config(['features.redlining' => false]);

        $this->assertFalse(Feature::enabled('redlining'));
        $this->assertTrue(Feature::disabled('redlining'));
    }

    public function test_feature_gate_returns_404_on_redline_session_page_when_disabled(): void
    {
        config(['features.redlining' => false]);

        $session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'status' => 'completed',
        ]);

        $this->actingAs($this->legalUser);

        $response = $this->get(
            "/admin/contracts/{$this->contract->id}/redline/{$session->id}"
        );

        // Feature disabled — Filament returns 403 (panel access denied) or 404
        $this->assertTrue(in_array($response->status(), [403, 404]),
            "Expected 403 or 404 when feature disabled, got {$response->status()}");
    }

    public function test_redline_session_model_progress_percentage(): void
    {
        $session = RedlineSession::factory()->create([
            'total_clauses' => 10,
            'reviewed_clauses' => 7,
        ]);

        $this->assertEquals(70, $session->progress_percentage);
    }

    public function test_redline_session_is_fully_reviewed(): void
    {
        $session = RedlineSession::factory()->create([
            'total_clauses' => 5,
            'reviewed_clauses' => 5,
        ]);

        $this->assertTrue($session->isFullyReviewed());

        $session->update(['reviewed_clauses' => 3]);
        $session->refresh();

        $this->assertFalse($session->isFullyReviewed());
    }

    public function test_redline_clause_has_material_change(): void
    {
        $unchanged = RedlineClause::factory()->create(['change_type' => 'unchanged']);
        $modification = RedlineClause::factory()->create(['change_type' => 'modification']);

        $this->assertFalse($unchanged->hasMaterialChange());
        $this->assertTrue($modification->hasMaterialChange());
    }
}
