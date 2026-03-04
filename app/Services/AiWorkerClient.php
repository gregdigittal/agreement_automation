<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
     * Downloads file from storage, base64-encodes it, sends to ai-worker.
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
            $disk = config('ccrs.contracts_disk', 'database');

            Log::info('AiWorkerClient: starting analysis', [
                'contract_id' => $contractId,
                'analysis_type' => $analysisType,
                'storage_path' => $storagePath,
                'file_name' => $fileName,
                'disk' => $disk,
                'ai_worker_url' => $this->baseUrl,
                'has_secret' => ! empty($this->secret),
                'timeout' => $this->timeout,
            ]);

            $fileContent = Storage::disk($disk)->get($storagePath);
            if ($fileContent === null || $fileContent === false) {
                throw new \RuntimeException("Could not download contract file from storage (disk={$disk}): {$storagePath}");
            }

            $fileSize = strlen($fileContent);
            Log::info('AiWorkerClient: file loaded from storage', [
                'contract_id' => $contractId,
                'file_size_bytes' => $fileSize,
                'base64_size_bytes' => (int) ceil($fileSize * 4 / 3),
            ]);

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

            $statusCode = $response->status();
            Log::info('AiWorkerClient: response received', [
                'contract_id' => $contractId,
                'status_code' => $statusCode,
                'response_size' => strlen($response->body()),
            ]);

            if ($response->failed()) {
                $body = $response->body();
                Log::error('AiWorkerClient: AI worker returned error', [
                    'contract_id' => $contractId,
                    'status_code' => $statusCode,
                    'response_body' => substr($body, 0, 2000),
                ]);
                // Throw with the actual error detail from AI worker
                $detail = 'AI worker returned HTTP ' . $statusCode;
                $json = $response->json();
                if (is_array($json) && isset($json['detail'])) {
                    $detail .= ': ' . (is_string($json['detail']) ? $json['detail'] : json_encode($json['detail']));
                } else {
                    $detail .= ': ' . substr($body, 0, 500);
                }
                throw new \RuntimeException($detail);
            }

            $result = $response->json();
            Log::info('AiWorkerClient: analysis completed', [
                'contract_id' => $contractId,
                'analysis_type' => $analysisType,
                'has_result' => isset($result['result']),
                'has_usage' => isset($result['usage']),
            ]);

            return $result;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('AiWorkerClient: connection failed — AI worker unreachable', [
                'contract_id' => $contractId,
                'url' => $this->baseUrl,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException("AI Worker unreachable at {$this->baseUrl}: " . $e->getMessage(), 0, $e);
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
     * Run redline analysis — compare contract text against template text.
     * The AI Worker stores clause results directly in MySQL.
     */
    public function redlineAnalyze(
        string $contractText,
        string $templateText,
        string $contractId,
        string $sessionId,
    ): array {
        $span = TelemetryService::startSpan('ai_worker.redline_analyze', [
            'contract_id' => $contractId,
            'session_id' => $sessionId,
        ]);
        try {
            $response = Http::withHeaders([
                    'X-AI-Worker-Secret' => $this->secret,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(600) // Redline analysis can take longer for large contracts
                ->post("{$this->baseUrl}/analyze-redline", [
                    'contract_text' => $contractText,
                    'template_text' => $templateText,
                    'contract_id' => $contractId,
                    'session_id' => $sessionId,
                ]);

            $response->throw();
            return $response->json();
        } finally {
            $span?->end();
        }
    }

    /**
     * Health check — test connectivity to AI worker.
     */
    public function health(): array
    {
        $response = Http::timeout(5)->get("{$this->baseUrl}/health");
        return $response->json();
    }
}
