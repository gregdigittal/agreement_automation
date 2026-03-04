<?php

namespace App\Console\Commands;

use App\Services\AiWorkerClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestAiWorker extends Command
{
    protected $signature = 'ccrs:test-ai-worker';
    protected $description = 'Test connectivity and configuration of the AI Worker sidecar';

    public function handle(): int
    {
        $url = config('ccrs.ai_worker_url');
        $hasSecret = ! empty(config('ccrs.ai_worker_secret'));
        $timeout = config('ccrs.ai_analysis_timeout', 120);
        $disk = config('ccrs.contracts_disk', 'database');

        $this->info('AI Worker Configuration');
        $this->table(['Setting', 'Value'], [
            ['AI_WORKER_URL', $url ?: '(empty)'],
            ['AI_WORKER_SECRET', $hasSecret ? '***set***' : '(empty — PROBLEM!)'],
            ['AI_ANALYSIS_TIMEOUT', $timeout . 's'],
            ['CCRS_STORAGE_DISK', $disk],
        ]);

        // Test 1: Health check
        $this->newLine();
        $this->info('Test 1: Health Check');
        try {
            $response = Http::timeout(5)->get(rtrim($url, '/') . '/health');
            if ($response->successful()) {
                $data = $response->json();
                $this->line('  ✅ AI Worker is responding');
                $this->line('  Model: ' . ($data['model'] ?? 'unknown'));
                $this->line('  Status: ' . ($data['status'] ?? 'unknown'));
            } else {
                $this->error('  ❌ AI Worker returned HTTP ' . $response->status());
                $this->line('  Body: ' . substr($response->body(), 0, 500));
                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Cannot reach AI Worker: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Test 2: Auth check (POST to /analyze with minimal payload to test auth)
        $this->newLine();
        $this->info('Test 2: Authentication');
        try {
            $response = Http::withHeaders([
                'X-AI-Worker-Secret' => config('ccrs.ai_worker_secret'),
                'Content-Type' => 'application/json',
            ])
                ->timeout(10)
                ->post(rtrim($url, '/') . '/analyze', [
                    'contract_id' => 'test-000',
                    'analysis_type' => 'summary',
                    'file_content_base64' => base64_encode('This is a test document.'),
                    'file_name' => 'test.txt',
                    'context' => [],
                ]);

            $status = $response->status();
            if ($status === 401) {
                $this->error('  ❌ Authentication failed — AI_WORKER_SECRET mismatch between app and worker');
                return self::FAILURE;
            } elseif ($status === 422) {
                // 422 means "could not extract text" — but auth passed and endpoint works!
                $this->line('  ✅ Authentication passed (endpoint responded with 422 — expected for test payload)');
            } elseif ($response->successful()) {
                $this->line('  ✅ Authentication passed — analysis returned successfully');
                $data = $response->json();
                $model = $data['usage']['model_used'] ?? 'unknown';
                $this->line('  Model used: ' . $model);
            } else {
                $body = $response->json();
                $detail = $body['detail'] ?? $response->body();
                $this->warn("  ⚠️  Auth passed but analysis returned HTTP {$status}: {$detail}");

                // Check for common model name errors
                if (is_string($detail) && (str_contains($detail, 'model') || str_contains($detail, 'not_found'))) {
                    $this->error('  → This looks like an invalid model name issue.');
                    $this->line('  → Current AI_MODEL in K8s: check "AI_MODEL" env var on ai-worker container');
                }
            }
        } catch (\Exception $e) {
            $this->error('  ❌ Request failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        // Test 3: Storage disk check
        $this->newLine();
        $this->info('Test 3: Storage Disk');
        try {
            $files = \Illuminate\Support\Facades\Storage::disk($disk)->files('contracts');
            $this->line("  ✅ Storage disk '{$disk}' accessible — " . count($files) . ' files in contracts/');
        } catch (\Exception $e) {
            $this->warn("  ⚠️  Storage disk '{$disk}' error: " . $e->getMessage());
        }

        // Test 4: Queue check
        $this->newLine();
        $this->info('Test 4: Queue (Redis)');
        try {
            $connection = config('queue.default');
            $size = \Illuminate\Support\Facades\Queue::size();
            $this->line("  ✅ Queue connection '{$connection}' is working — {$size} jobs pending");
        } catch (\Exception $e) {
            $this->error("  ❌ Queue error: " . $e->getMessage());
        }

        $this->newLine();
        $this->info('Done. If all tests pass but analysis still fails, check the AI worker container logs:');
        $this->line('  kubectl logs <pod> -c ai-worker -n <namespace>');

        return self::SUCCESS;
    }
}
