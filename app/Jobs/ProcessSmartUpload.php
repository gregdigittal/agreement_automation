<?php

namespace App\Jobs;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSmartUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public readonly string $contractId,
        public readonly ?string $actorId = null,
    ) {}

    public function handle(): void
    {
        $contract = Contract::find($this->contractId);

        if (! $contract) {
            Log::error('ProcessSmartUpload: contract not found', ['contract_id' => $this->contractId]);
            return;
        }

        if (! $contract->storage_path) {
            Log::error('ProcessSmartUpload: no storage_path', ['contract_id' => $this->contractId]);
            return;
        }

        Log::info('ProcessSmartUpload: dispatching AI analysis', [
            'contract_id' => $this->contractId,
        ]);

        // Dispatch extraction (title, contract_type, dates, key fields)
        ProcessAiAnalysis::dispatch(
            contractId: $this->contractId,
            analysisType: 'extraction',
            actorId: $this->actorId,
        )->onQueue('default');

        // Dispatch discovery (counterparty, entity, governing_law, jurisdiction)
        ProcessAiAnalysis::dispatch(
            contractId: $this->contractId,
            analysisType: 'discovery',
            actorId: $this->actorId,
        )->onQueue('default');
    }
}
