<?php

namespace App\Console\Commands;

use App\Models\AiAnalysisResult;
use App\Models\AiDiscoveryDraft;
use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class DiagnoseAiPipeline extends Command
{
    protected $signature = 'ccrs:diagnose-ai
        {contract? : Contract ID to diagnose (optional — checks infrastructure only if omitted)}
        {--recent : Show the 10 most recent AI analysis results across all contracts}';

    protected $description = 'Comprehensive diagnostic of the AI analysis pipeline — checks every component boundary';

    public function handle(): int
    {
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║   CCRS AI Pipeline Diagnostics                         ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->newLine();

        $ok = true;

        // ── 1. Redis ──────────────────────────────────────────────
        $this->info('1. Redis Connectivity');
        try {
            $pong = Redis::ping();
            $this->line("   ✅ Redis PING → {$pong}");
        } catch (\Exception $e) {
            $this->error("   ❌ Redis unreachable: {$e->getMessage()}");
            $ok = false;
        }

        // ── 2. Queue connection ───────────────────────────────────
        $this->newLine();
        $this->info('2. Queue Connection');
        $queueConn = config('queue.default');
        $this->line("   Driver: {$queueConn}");
        try {
            $size = Queue::size();
            $this->line("   ✅ Queue accessible — {$size} job(s) on default queue");

            // Check specific queues
            foreach (['default', 'high', 'low'] as $queueName) {
                try {
                    $queueSize = Queue::size($queueName);
                    if ($queueSize > 0) {
                        $this->line("   Queue '{$queueName}': {$queueSize} job(s) pending");
                    }
                } catch (\Exception $e) {
                    // Ignore — queue may not exist
                }
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Queue error: {$e->getMessage()}");
            $ok = false;
        }

        // ── 3. Horizon status ─────────────────────────────────────
        $this->newLine();
        $this->info('3. Horizon Worker Status');
        try {
            // Check if Horizon master process is running
            $horizonStatus = 'unknown';
            try {
                $horizonStatus = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();
                if (empty($horizonStatus)) {
                    $this->error('   ❌ Horizon is NOT running — no master supervisor found');
                    $this->line('      Jobs will pile up in Redis but never be processed.');
                    $this->line('      Check: supervisord → queue-worker process');
                    $ok = false;
                } else {
                    $this->line('   ✅ Horizon master supervisor(s) running: ' . count($horizonStatus));
                    foreach ($horizonStatus as $master) {
                        $this->line("      Name: {$master->name}, PID: {$master->pid}, Status: {$master->status}");
                    }
                }
            } catch (\Exception $e) {
                $this->warn("   ⚠️  Cannot check Horizon status: {$e->getMessage()}");
            }

            // Check Horizon's recent job metrics
            try {
                $recentJobs = DB::table('jobs')->count();
                $failedJobs = DB::table('failed_jobs')->count();
                $this->line("   Jobs table: {$recentJobs} pending");
                $this->line("   Failed jobs table: {$failedJobs} failed");
            } catch (\Exception $e) {
                // Database queue driver tables may not exist if using Redis exclusively
                $this->line("   (jobs/failed_jobs tables not checked — using Redis driver)");
            }

            // Check failed jobs in Redis via Horizon
            try {
                $failedRepo = app(\Laravel\Horizon\Contracts\JobRepository::class);
                $recentFailed = $failedRepo->getFailed(0, 5);
                if ($recentFailed->isNotEmpty()) {
                    $this->warn("   ⚠️  Recent failed jobs in Horizon:");
                    foreach ($recentFailed as $job) {
                        $name = $job->name ?? 'unknown';
                        $failedAt = $job->failed_at ?? '?';
                        $exception = isset($job->exception) ? substr($job->exception, 0, 120) : 'no exception';
                        $this->line("      [{$failedAt}] {$name}: {$exception}");
                    }
                } else {
                    $this->line('   ✅ No recent failed jobs in Horizon');
                }
            } catch (\Exception $e) {
                $this->line("   (Could not check Horizon failed jobs: {$e->getMessage()})");
            }
        } catch (\Exception $e) {
            $this->warn("   ⚠️  Horizon check error: {$e->getMessage()}");
        }

        // ── 4. AI Worker Health ───────────────────────────────────
        $this->newLine();
        $this->info('4. AI Worker Sidecar');
        $aiUrl = config('ccrs.ai_worker_url');
        $aiSecret = config('ccrs.ai_worker_secret');
        $this->line("   URL: {$aiUrl}");
        $this->line("   Secret configured: " . (! empty($aiSecret) ? 'yes' : '❌ NO'));

        try {
            $health = Http::timeout(5)->get(rtrim($aiUrl, '/') . '/health');
            if ($health->successful()) {
                $data = $health->json();
                $this->line("   ✅ Health OK — model: " . ($data['model'] ?? 'unknown') . ", status: " . ($data['status'] ?? 'unknown'));
            } else {
                $this->error("   ❌ Health check returned HTTP {$health->status()}");
                $ok = false;
            }
        } catch (\Exception $e) {
            $this->error("   ❌ AI Worker unreachable: {$e->getMessage()}");
            $ok = false;
        }

        // ── 5. Storage disk ───────────────────────────────────────
        $this->newLine();
        $this->info('5. Storage Disk');
        $disk = config('ccrs.contracts_disk', 'database');
        $this->line("   Configured disk: {$disk}");
        try {
            $contractFiles = Storage::disk($disk)->files('contracts');
            $this->line("   ✅ Disk accessible — " . count($contractFiles) . " file(s) in contracts/");
        } catch (\Exception $e) {
            $this->error("   ❌ Storage disk error: {$e->getMessage()}");
            $ok = false;
        }

        // ── 6. Recent AI analysis results (global) ────────────────
        if ($this->option('recent')) {
            $this->newLine();
            $this->info('6. Recent AI Analysis Results (last 10)');
            $recent = AiAnalysisResult::orderByDesc('created_at')->limit(10)->get();
            if ($recent->isEmpty()) {
                $this->warn('   ⚠️  No AI analysis results in database at all!');
                $this->line('      This means either: no analyses have been triggered, OR');
                $this->line('      Horizon is not processing jobs (jobs never reach handle()).');
            } else {
                $rows = $recent->map(fn ($r) => [
                    substr($r->contract_id, 0, 8) . '…',
                    $r->analysis_type,
                    $r->status,
                    $r->error_message ? substr($r->error_message, 0, 50) : '—',
                    $r->model_used ?? '—',
                    $r->created_at?->diffForHumans() ?? '—',
                ])->toArray();
                $this->table(['Contract', 'Type', 'Status', 'Error', 'Model', 'When'], $rows);
            }
        }

        // ── 7. Contract-specific diagnosis ────────────────────────
        $contractId = $this->argument('contract');
        if ($contractId) {
            $this->newLine();
            $this->info("7. Contract-Specific Diagnosis: {$contractId}");

            $contract = Contract::find($contractId);
            if (! $contract) {
                $this->error("   ❌ Contract not found: {$contractId}");
                return self::FAILURE;
            }

            $this->line("   Title: " . ($contract->title ?? '(untitled)'));
            $this->line("   Workflow state: {$contract->workflow_state}");
            $this->line("   Storage path: " . ($contract->storage_path ?? '❌ NONE'));
            $this->line("   File name: " . ($contract->file_name ?? '(null)'));

            if ($contract->storage_path) {
                try {
                    $exists = Storage::disk($disk)->exists($contract->storage_path);
                    $size = $exists ? Storage::disk($disk)->size($contract->storage_path) : 0;
                    if ($exists) {
                        $this->line("   ✅ File exists on disk — " . number_format($size) . " bytes");
                    } else {
                        $this->error("   ❌ File NOT found on disk: {$contract->storage_path}");
                        $ok = false;
                    }
                } catch (\Exception $e) {
                    $this->error("   ❌ File check error: {$e->getMessage()}");
                }
            }

            // AI Analysis Results for this contract
            $this->newLine();
            $this->info('   AI Analysis Results for this contract:');
            $analyses = AiAnalysisResult::where('contract_id', $contractId)
                ->orderByDesc('created_at')
                ->get();

            if ($analyses->isEmpty()) {
                $this->error('   ❌ ZERO analysis results for this contract!');
                $this->line('      This means ProcessAiAnalysis::handle() never ran.');
                $this->line('      Likely cause: Horizon is not running OR jobs are stuck in Redis.');
                $this->newLine();
                $this->line('      Next steps:');
                $this->line('      1. Check if Horizon master is running (section 3 above)');
                $this->line('      2. Check Redis queue size: redis-cli LLEN queues:default');
                $this->line('      3. Check Horizon dashboard at /horizon');
            } else {
                $rows = $analyses->map(fn ($a) => [
                    $a->analysis_type,
                    $a->status,
                    $a->error_message ? substr($a->error_message, 0, 60) : '—',
                    $a->model_used ?? '—',
                    $a->confidence_score ?? '—',
                    $a->processing_time_ms ? round($a->processing_time_ms / 1000, 1) . 's' : '—',
                    $a->created_at?->format('Y-m-d H:i') ?? '—',
                ])->toArray();
                $this->table(['Type', 'Status', 'Error', 'Model', 'Confidence', 'Time', 'Created'], $rows);

                // Summarize statuses
                $statusCounts = $analyses->groupBy('status')->map->count();
                $this->line("   Summary: " . $statusCounts->map(fn ($c, $s) => "{$s}={$c}")->join(', '));

                // Check for the silent-empty-discovery problem
                $discoveryAnalyses = $analyses->where('analysis_type', 'discovery');
                foreach ($discoveryAnalyses as $da) {
                    if ($da->status === 'completed') {
                        $result = $da->result;
                        $discoveryCount = is_array($result) && isset($result['discoveries'])
                            ? count($result['discoveries'])
                            : 'N/A';
                        $this->newLine();
                        $this->info("   Discovery analysis (id: {$da->id}):");
                        $this->line("   Discoveries in result JSON: {$discoveryCount}");
                        if ($discoveryCount === 0 || $discoveryCount === 'N/A') {
                            $this->warn("   ⚠️  ZERO discoveries found — AI may have returned empty result");
                            $this->line("   Result JSON preview: " . substr(json_encode($result), 0, 300));
                        }
                    } elseif ($da->status === 'processing') {
                        $age = $da->created_at?->diffInMinutes(now()) ?? 0;
                        if ($age > 5) {
                            $this->warn("   ⚠️  Discovery analysis stuck in 'processing' for {$age} minutes!");
                        }
                    }
                }
            }

            // AI Discovery Drafts for this contract
            $this->newLine();
            $this->info('   AI Discovery Drafts for this contract:');
            $drafts = AiDiscoveryDraft::where('contract_id', $contractId)->get();
            if ($drafts->isEmpty()) {
                $this->warn('   ⚠️  No discovery drafts exist for this contract.');
                $this->line('      This means either: discovery analysis was never run,');
                $this->line('      OR it completed but found zero entities in the document.');
            } else {
                $draftRows = $drafts->map(fn ($d) => [
                    $d->draft_type,
                    $d->status,
                    number_format($d->confidence, 2),
                    $d->matched_record_id ? 'yes' : 'no',
                    substr(json_encode($d->extracted_data), 0, 60),
                ])->toArray();
                $this->table(['Type', 'Status', 'Confidence', 'Matched?', 'Data Preview'], $draftRows);
            }
        }

        // ── Summary ───────────────────────────────────────────────
        $this->newLine();
        if (! $contractId) {
            $this->info('💡 Tip: Run with a contract ID for full diagnosis:');
            $this->line('   php artisan ccrs:diagnose-ai <contract-id>');
            $this->line('   php artisan ccrs:diagnose-ai --recent   (show last 10 results)');
        }

        $this->newLine();
        if ($ok) {
            $this->info('✅ Infrastructure checks passed.');
        } else {
            $this->error('❌ Some infrastructure checks FAILED — see above.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
