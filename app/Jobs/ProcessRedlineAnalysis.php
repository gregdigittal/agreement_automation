<?php

namespace App\Jobs;

use App\Models\RedlineSession;
use App\Services\AiWorkerClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessRedlineAnalysis implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600;

    public function __construct(
        public string $sessionId,
    ) {}

    public function handle(AiWorkerClient $aiClient): void
    {
        $session = RedlineSession::with(['contract', 'wikiContract'])->find($this->sessionId);

        if (!$session) {
            Log::error("ProcessRedlineAnalysis: session {$this->sessionId} not found");
            return;
        }

        $contract = $session->contract;
        $wikiContract = $session->wikiContract;

        try {
            // 1. Extract contract text from uploaded file
            $contractText = $this->extractText($contract->storage_path);
            if (empty($contractText)) {
                throw new \RuntimeException(
                    "Failed to extract text from contract file: {$contract->storage_path}"
                );
            }

            // 2. Extract template text from WikiContract
            $templateText = '';
            if ($wikiContract && $wikiContract->storage_path) {
                $templateText = $this->extractText($wikiContract->storage_path);
            }

            if (empty($templateText)) {
                throw new \RuntimeException(
                    'No template text available — WikiContract has no storage_path or extractable content.'
                );
            }

            // 3. Call AI Worker /analyze-redline endpoint
            $aiClient->redlineAnalyze(
                contractText: $contractText,
                templateText: $templateText,
                contractId: $contract->id,
                sessionId: $session->id,
            );

            Log::info("ProcessRedlineAnalysis: completed for session {$this->sessionId}");

        } catch (\Throwable $e) {
            Log::error("ProcessRedlineAnalysis failed: {$e->getMessage()}", [
                'session_id' => $this->sessionId,
                'exception' => $e,
            ]);

            $session->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
            ]);

            throw $e;
        }
    }

    /**
     * Extract plain text from a PDF or DOCX file stored in S3.
     */
    private function extractText(string $storagePath): string
    {
        $disk = config('ccrs.contracts_disk', 's3');
        $contents = Storage::disk($disk)->get($storagePath);

        if (!$contents) {
            return '';
        }

        $extension = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));

        $tempPath = tempnam(sys_get_temp_dir(), 'redline_') . '.' . $extension;
        file_put_contents($tempPath, $contents);

        try {
            if ($extension === 'pdf') {
                $outputLines = [];
                $exitCode = 0;
                exec("pdftotext " . escapeshellarg($tempPath) . " -", $outputLines, $exitCode);
                if ($exitCode === 0) {
                    return implode("\n", $outputLines);
                }
                return '';
            }

            if (in_array($extension, ['docx', 'doc'])) {
                $outputLines = [];
                $exitCode = 0;
                exec(
                    "python3 -c \"import docx; " .
                    "doc = docx.Document(" . escapeshellarg($tempPath) . "); " .
                    "print('\\n'.join(p.text for p in doc.paragraphs))\"",
                    $outputLines,
                    $exitCode
                );
                if ($exitCode === 0) {
                    return implode("\n", $outputLines);
                }
                return '';
            }

            // Plain text fallback
            return $contents;
        } finally {
            @unlink($tempPath);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $session = RedlineSession::find($this->sessionId);
        if ($session && $session->status !== 'failed') {
            $session->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
        }
    }
}
