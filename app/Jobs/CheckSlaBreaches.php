<?php
namespace App\Jobs;

use App\Services\EscalationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckSlaBreaches implements ShouldQueue
{
    use Queueable;
    public function handle(EscalationService $service): void { $service->checkBreaches(); }
}
