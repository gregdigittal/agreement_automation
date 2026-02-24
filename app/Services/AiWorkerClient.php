<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\RequestException;
use App\Services\TelemetryService;

class AiWorkerClient
{
    private string $baseUrl;
    private string $secret;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('ccrs.ai_worker_url'), '/');
        $this->secret = config('ccrs.ai_worker_secret');
        $this->timeout = config('ccrs.ai_analysis_timeout', 120);
    }

    /**
     * Run AI analysis on a contract file.
     * Downloads file from S3, base64-encodes it, sends to ai-worker.
     * Returns result and usage data. Does NOT write to DB.
     */
    public function analyze(
        string $contractId,
        string $analysisType,
        string $storagePath,
        string $fileName,
        array $context = []
    ): array {
        $span = TelemetryService::startSpan('ai_worker.analyze', [
            'contract_id' => $contractId,
            'analysis_type' => $analysisType,
        ]);
        try {
            $disk = config('ccrs.contracts_disk', 's3');
            $fileContent = Storage::disk($disk)->get($storagePath);
            if ($fileContent === null || $fileContent === false) {
                throw new \RuntimeException("Could not download contract file from storage: {$storagePath}");
            }

            $response = Http::withHeaders([
                    'X-AI-Worker-Secret' => $this->secret,
                    'Content-Type' => 'application/json',
                ])
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/analyze", [
                    'contract_id' => $contractId,
                    'analysis_type' => $analysisType,
                    'file_content_base64' => base64_encode($fileContent),
                    'file_name' => $fileName,
                    'context' => $context,
                ]);

            $response->throw();
            return $response->json();
        } finally {
            $span?->end();
        }
    }

    /**
     * Generate a workflow template using AI.
     */
    public function generateWorkflow(string $description, ?string $regionId = null, ?string $entityId = null, ?string $projectId = null): array
    {
        $response = Http::withHeaders(['X-AI-Worker-Secret' => $this->secret])
            ->timeout(60)
            ->post("{$this->baseUrl}/generate-workflow", [
                'description' => $description,
                'region_id' => $regionId,
                'entity_id' => $entityId,
                'project_id' => $projectId,
            ]);

        $response->throw();
        return $response->json();
    }

    /**
     * Health check.
     */
    public function health(): array
    {
        $response = Http::timeout(5)->get("{$this->baseUrl}/health");
        return $response->json();
    }
}
