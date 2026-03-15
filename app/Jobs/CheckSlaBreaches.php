<?php

namespace App\Jobs;

use App\Services\EscalationService;
use Illuminate\Support\Facades\Log;

class CheckSlaBreaches extends TenantAwareJob
{
    public function handle(EscalationService $service): void
    {
        $count = $service->checkSlaBreaches();
        Log::info("Checked SLA breaches, escalated {$count}");
    }
}
