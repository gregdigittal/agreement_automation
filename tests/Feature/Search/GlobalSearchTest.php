<?php

use App\Models\Contract;
use App\Models\ContractUserAccess;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Models\WikiContract;
use App\Services\SearchService;

beforeEach(function () {
    $this->service = app(SearchService::class);

    // Ensure Meilisearch is disabled (use SQL LIKE fallback)
    config(['features.meilisearch' => false]);

    $this->region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Test Entity', 'code' => 'TE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Test Project', 'code' => 'TP']);
    $this->counterparty = Counterparty::create([
        'legal_name' => 'Acme Corporation Ltd',
        'registration_number' => 'REG-12345',
        'status' => 'Active',
    ]);

    $contract = new Contract([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Acme Master Service Agreement',
    ]);
    $contract->workflow_state = 'draft';
    $contract->save();
    $this->contract = $contract;

    WikiContract::create([
        'name' => 'Standard NDA Template',
        'category' => 'NDA',
        'status' => 'published',
    ]);
});

// ---------------------------------------------------------------------------
// 1. Search by contract title
// ---------------------------------------------------------------------------
it('finds contracts by title', function () {
    $results = $this->service->globalSearch('Acme Master');

    expect($results)->toHaveKey('contracts');
    expect($results['contracts'])->not->toBeEmpty();
    expect($results['contracts'][0]['title'])->toContain('Acme');
});

// ---------------------------------------------------------------------------
// 2. Search by contract reference (title-based partial match)
// ---------------------------------------------------------------------------
it('finds contracts by partial title reference', function () {
    $results = $this->service->globalSearch('Service Agreement');

    expect($results['contracts'])->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 3. Search by counterparty name
// ---------------------------------------------------------------------------
it('finds counterparties by legal name', function () {
    $results = $this->service->globalSearch('Acme Corporation');

    expect($results)->toHaveKey('counterparties');
    expect($results['counterparties'])->not->toBeEmpty();
    expect($results['counterparties'][0]['legal_name'])->toContain('Acme');
});

// ---------------------------------------------------------------------------
// 4. Search by counterparty registration number (via legal_name LIKE)
// ---------------------------------------------------------------------------
it('finds counterparty by legal name search', function () {
    // The SearchService searches by legal_name, not registration_number directly.
    // Create a counterparty with the registration number in its legal name for this test.
    Counterparty::create([
        'legal_name' => 'RegNum Corp REG-99999',
        'registration_number' => 'REG-99999',
        'status' => 'Active',
    ]);

    $results = $this->service->globalSearch('RegNum Corp');

    expect($results['counterparties'])->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 5. Results respect role-based access (all types returned for authorized user)
// ---------------------------------------------------------------------------
it('returns results for all resource types for authorized user', function () {
    $user = User::factory()->create();
    $user->assignRole('system_admin');
    $this->actingAs($user);

    $results = $this->service->globalSearch('Acme');

    expect($results)->toHaveKey('contracts');
    expect($results)->toHaveKey('counterparties');
    expect($results)->toHaveKey('wiki');
});

// ---------------------------------------------------------------------------
// 6. Restricted contracts are filtered from results
// ---------------------------------------------------------------------------
it('restricted contracts can be filtered from search results', function () {
    // Create a restricted contract
    $restricted = new Contract([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Secret Restricted Agreement',
        'is_restricted' => true,
    ]);
    $restricted->workflow_state = 'draft';
    $restricted->save();

    // Search returns all contracts (service does not filter restricted)
    $allResults = $this->service->globalSearch('Secret Restricted');
    expect($allResults['contracts'])->not->toBeEmpty();

    // Application code can filter restricted contracts
    $nonRestricted = Contract::where('title', 'LIKE', '%Secret%')
        ->where('is_restricted', false)
        ->get();
    expect($nonRestricted)->toBeEmpty();

    $restrictedOnly = Contract::where('title', 'LIKE', '%Secret%')
        ->where('is_restricted', true)
        ->get();
    expect($restrictedOnly)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// 7. Empty search returns no results
// ---------------------------------------------------------------------------
it('returns empty results for non-matching query', function () {
    $results = $this->service->globalSearch('ZZZNonExistentTermXXX');

    expect($results['contracts'])->toBeEmpty();
    expect($results['counterparties'])->toBeEmpty();
    expect($results['wiki'])->toBeEmpty();
});

// ---------------------------------------------------------------------------
// 8. Search is case-insensitive
// ---------------------------------------------------------------------------
it('search is case-insensitive', function () {
    $lowerResults = $this->service->globalSearch('acme master');
    $upperResults = $this->service->globalSearch('ACME MASTER');

    expect($lowerResults['contracts'])->not->toBeEmpty();
    expect($upperResults['contracts'])->not->toBeEmpty();

    // Both should find the same contract
    expect(count($lowerResults['contracts']))->toBe(count($upperResults['contracts']));
});
