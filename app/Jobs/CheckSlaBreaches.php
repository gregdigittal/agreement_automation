<?php

namespace App\Jobs;

use App\Services\EscalationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckSlaBreaches implements ShouldQueue
{
    use Queueable;

    public function handle(EscalationService $service): void
    {
        $count = $service->checkSlaBreaches();
        Log::info("Checked SLA breaches, escalated {$count}");
    }
}
