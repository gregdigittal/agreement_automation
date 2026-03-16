<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Removes all E2E test fixtures created by `e2e:seed`.
 * Safe to run multiple times (idempotent).
 */
class E2ETeardownCommand extends Command
{
    protected $signature = 'e2e:teardown';

    protected $description = 'Remove E2E test fixtures (e2e environment only)';

    public function handle(E2ESeedCommand $seeder): int
    {
        if (! in_array(app()->environment(), ['e2e', 'local', 'testing'])) {
            $this->error('e2e:teardown must not be run outside e2e/local/testing environments.');

            return self::FAILURE;
        }

        $this->info('[E2E] Removing test fixtures…');
        $seeder->teardown();
        $this->info('[E2E] Done.');

        return self::SUCCESS;
    }
}
