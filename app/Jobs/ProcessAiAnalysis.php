<?php
namespace App\Jobs;

use App\Models\AiAnalysisResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAiAnalysis implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $contractId, public string $analysisType) {}

    public function handle(): void
    {
        // Stub — full implementation in Phase C (AI Worker integration)
        AiAnalysisResult::create([
            'contract_id' => $this->contractId,
            'analysis_type' => $this->analysisType,
            'status' => 'pending',
        ]);
    }
}
