<?php

use App\Models\Contract;
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

    $region = Region::create(['name' => 'Search Region', 'code' => 'SR']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Search Entity', 'code' => 'SE']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Search Project', 'code' => 'SP']);
    $counterparty = Counterparty::create(['legal_name' => 'Acme Search Corp', 'status' => 'Active']);

    $contract = new Contract([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => $counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Acme Master Service Agreement',
    ]);
    $contract->workflow_state = 'draft';
    $contract->save();

    WikiContract::create([
        'name' => 'Acme Wiki Template',
        'category' => 'Commercial',
        'status' => 'published',
    ]);
});

it('global search returns contracts matching title', function () {
    $results = $this->service->globalSearch('Acme');

    expect($results)->toHaveKey('contracts');
    expect($results['contracts'])->not->toBeEmpty();
});

it('global search returns counterparties matching legal_name', function () {
    $results = $this->service->globalSearch('Acme Search Corp');

    expect($results)->toHaveKey('counterparties');
    expect($results['counterparties'])->not->toBeEmpty();
});

it('global search returns wiki contracts matching name', function () {
    $results = $this->service->globalSearch('Acme Wiki');

    expect($results)->toHaveKey('wiki');
    expect($results['wiki'])->not->toBeEmpty();
});

it('query with no matches returns empty results', function () {
    $results = $this->service->globalSearch('ZZZNonExistentTermXXX');

    expect($results['contracts'])->toBeEmpty();
    expect($results['counterparties'])->toBeEmpty();
    expect($results['wiki'])->toBeEmpty();
});
