<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class QueueHealthCheck extends Command
{
    protected $signature = 'queue:health';
    protected $description = 'Check if the Redis queue connection is reachable (K8s liveness probe)';

    public function handle(): int
    {
        try {
            Redis::connection('queue')->ping();
            $this->info('Queue connection healthy.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Queue connection failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
