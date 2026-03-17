<?php

namespace Tests\Feature\Redline;

use App\Jobs\ProcessRedlineAnalysis;
use App\Models\Contract;
use App\Models\RedlineSession;
use App\Models\WikiContract;
use App\Services\AiWorkerClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessRedlineAnalysisJobTest extends TestCase
{
    use RefreshDatabase;

    private Contract $contract;

    private WikiContract $template;

    private RedlineSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.redlining' => true]);
        Storage::fake('database');

        $this->contract = Contract::factory()->create([
            'storage_path' => 'contracts/test-contract.txt',
        ]);

        $this->template = WikiContract::factory()->published()->create([
            'region_id' => $this->contract->region_id,
            'storage_path' => 'templates/test-template.txt',
        ]);

        // Store fake text files so extractText() returns non-empty content
        Storage::disk('database')->put('contracts/test-contract.txt', 'Contract clause content for testing.');
        Storage::disk('database')->put('templates/test-template.txt', 'Template standard clause for testing.');

        $this->session = RedlineSession::factory()->create([
            'contract_id' => $this->contract->id,
            'wiki_contract_id' => $this->template->id,
            'status' => 'pending',
        ]);
    }

    public function test_job_sets_session_to_processing_then_completed_on_success(): void
    {
        $aiClient = $this->createMock(AiWorkerClient::class);
        $aiClient->expects($this->once())
            ->method('redlineAnalyze')
            ->willReturn([]);

        $job = new ProcessRedlineAnalysis($this->session->id);
        $job->handle($aiClient);

        $this->session->refresh();
        $this->assertEquals('completed', $this->session->status);
    }

    public function test_job_calls_ai_worker_client_not_http_directly(): void
    {
        $aiClient = $this->createMock(AiWorkerClient::class);
        $aiClient->expects($this->once())
            ->method('redlineAnalyze')
            ->with(
                $this->stringContains('Contract clause content'),
                $this->stringContains('Template standard clause'),
                $this->contract->id,
                $this->session->id,
            )
            ->willReturn([]);

        $job = new ProcessRedlineAnalysis($this->session->id);
        $job->handle($aiClient);
    }

    public function test_job_sets_session_to_failed_on_ai_worker_exception(): void
    {
        $aiClient = $this->createMock(AiWorkerClient::class);
        $aiClient->method('redlineAnalyze')
            ->willThrowException(new \RuntimeException('AI worker timeout'));

        $job = new ProcessRedlineAnalysis($this->session->id);

        try {
            $job->handle($aiClient);
        } catch (\RuntimeException) {
            // exception expected
        }

        $this->session->refresh();
        $this->assertEquals('failed', $this->session->status);
        $this->assertStringContainsString('AI worker timeout', $this->session->error_message);
    }

    public function test_failed_hook_sets_session_to_failed_on_permanent_failure(): void
    {
        $this->session->update(['status' => 'processing']);

        $job = new ProcessRedlineAnalysis($this->session->id);
        $job->failed(new \RuntimeException('Permanent queue failure'));

        $this->session->refresh();
        $this->assertEquals('failed', $this->session->status);
    }

    public function test_failed_hook_does_not_override_already_failed_session(): void
    {
        $this->session->update([
            'status' => 'failed',
            'error_message' => 'Original failure reason',
        ]);

        $job = new ProcessRedlineAnalysis($this->session->id);
        $job->failed(new \RuntimeException('Second failure'));

        $this->session->refresh();
        // Message should remain unchanged — failed() guards against double-update
        $this->assertEquals('Original failure reason', $this->session->error_message);
    }

    public function test_job_throws_when_contract_has_no_storage_path(): void
    {
        $this->contract->update(['storage_path' => 'contracts/nonexistent.txt']);

        $aiClient = $this->createMock(AiWorkerClient::class);
        $aiClient->expects($this->never())->method('redlineAnalyze');

        $job = new ProcessRedlineAnalysis($this->session->id);

        $this->expectException(\RuntimeException::class);
        $job->handle($aiClient);

        $this->session->refresh();
        $this->assertEquals('failed', $this->session->status);
    }

    public function test_job_is_idempotent_on_completed_session(): void
    {
        // If AI worker already set status to completed before job runs
        $this->session->update(['status' => 'completed', 'total_clauses' => 3]);

        $aiClient = $this->createMock(AiWorkerClient::class);
        $aiClient->expects($this->once())
            ->method('redlineAnalyze')
            ->willReturn([]);

        $job = new ProcessRedlineAnalysis($this->session->id);
        $job->handle($aiClient);

        $this->session->refresh();
        // Status stays completed, not overwritten
        $this->assertEquals('completed', $this->session->status);
    }
}
