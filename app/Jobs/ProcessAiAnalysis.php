<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAiAnalysis implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $contractId,
        public string $analysisType
    ) {}

    public function handle(): void
    {
        // TODO: implement in Phase C
    }
}
