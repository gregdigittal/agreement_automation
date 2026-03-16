<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Services\SigningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Seeds the E2E database with known fixture data and writes a JSON fixture file
 * that Playwright tests can read to obtain valid tokens and entity IDs.
 *
 * Only callable in the `e2e` environment — exits with an error in production.
 */
class E2ESeedCommand extends Command
{
    protected $signature = 'e2e:seed';

    protected $description = 'Seed E2E test fixtures (e2e environment only)';

    public function handle(SigningService $signingService): int
    {
        if (! in_array(app()->environment(), ['e2e', 'local', 'testing'])) {
            $this->error('e2e:seed must not be run outside e2e/local/testing environments.');

            return self::FAILURE;
        }

        $this->info('[E2E] Cleaning up previous fixtures…');
        $this->teardown();

        $this->info('[E2E] Creating E2E test data…');

        // Seed roles (required by Spatie laravel-permission)
        $this->call('db:seed', ['--class' => 'Database\\Seeders\\RoleSeeder', '--force' => true]);

        // Region → Entity → Project hierarchy
        $region = Region::create(['name' => 'E2E Test Region']);
        $entity = Entity::create(['region_id' => $region->id, 'name' => 'E2E Test Entity']);
        $project = Project::create(['entity_id' => $entity->id, 'name' => 'E2E Test Project']);

        // Counterparty
        $counterparty = Counterparty::create([
            'legal_name' => 'E2E Test Counterparty',
            'status' => 'Active',
        ]);

        // User (system_admin)
        $user = User::create([
            'name' => 'E2E Admin User',
            'email' => 'e2e-admin@ccrs-test.local',
            'password' => Hash::make('E2E-test-password-123!'),
            'azure_id' => 'e2e-azure-id-test',
        ]);
        $user->assignRole('system_admin');

        // Contract — store a minimal stub PDF in the database disk
        $storagePath = 'contracts/e2e-test-contract.pdf';
        Storage::disk(config('ccrs.contracts_disk'))->put(
            $storagePath,
            '%PDF-1.4' . PHP_EOL . '1 0 obj<</Type /Catalog>>endobj' . PHP_EOL . '%%EOF',
        );

        $contract = Contract::create([
            'region_id' => $region->id,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'counterparty_id' => $counterparty->id,
            'contract_type' => 'Commercial',
            'title' => 'E2E Test Contract',
            'storage_path' => $storagePath,
            'file_name' => 'e2e-test-contract.pdf',
        ]);

        // Authenticate as the E2E user so SigningService can read auth()->id()
        Auth::loginUsingId($user->id);

        // Signing session + token
        $session = $signingService->createSession($contract, [
            [
                'name' => 'E2E Signer',
                'email' => 'e2e-signer@ccrs-test.local',
                'type' => 'external',
                'order' => 0,
            ],
        ], 'sequential');

        $signer = $session->signers->first();
        $rawToken = $signingService->sendToSigner($signer);

        // Write fixture file for Playwright tests
        $fixtures = [
            'signing_token' => $rawToken,
            'contract_id' => $contract->id,
            'contract_title' => $contract->title,
            'signer_name' => $signer->signer_name,
            'signer_email' => $signer->signer_email,
            'user_id' => $user->id,
            'user_email' => $user->email,
            'region_id' => $region->id,
        ];

        $fixturesPath = base_path('e2e/fixtures/seeded.json');
        file_put_contents($fixturesPath, json_encode($fixtures, JSON_PRETTY_PRINT));

        $this->info('[E2E] Fixtures written to ' . $fixturesPath);
        $this->line(json_encode($fixtures, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }

    /**
     * Remove all E2E fixture records (identified by the e2e-test naming pattern).
     * Called at the start of seed (for cleanup) and directly by the teardown command.
     * Uses PRAGMA foreign_keys = OFF to simplify deletion order.
     */
    public function teardown(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        $contractIds = DB::table('contracts')->where('title', 'E2E Test Contract')->pluck('id');

        if ($contractIds->isNotEmpty()) {
            DB::table('signing_session_signers')
                ->join('signing_sessions', 'signing_sessions.id', '=', 'signing_session_signers.signing_session_id')
                ->whereIn('signing_sessions.contract_id', $contractIds)
                ->delete();
            DB::table('signing_sessions')->whereIn('contract_id', $contractIds)->delete();
            DB::table('contracts')->whereIn('id', $contractIds)->delete();
        }

        DB::table('users')->where('email', 'like', '%@ccrs-test.local')->delete();
        DB::table('counterparties')->where('legal_name', 'E2E Test Counterparty')->delete();
        DB::table('projects')->where('name', 'E2E Test Project')->delete();
        DB::table('entities')->where('name', 'E2E Test Entity')->delete();
        DB::table('regions')->where('name', 'E2E Test Region')->delete();

        DB::statement('PRAGMA foreign_keys = ON');
    }
}
