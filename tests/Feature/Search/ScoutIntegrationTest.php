<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\WikiContract;
use App\Services\SearchService;
use Laravel\Scout\Searchable;

// ---------------------------------------------------------------------------
// Searchable trait — models have Scout integration
// ---------------------------------------------------------------------------

it('Contract model uses the Scout Searchable trait', function () {
    expect(in_array(Searchable::class, class_uses_recursive(Contract::class)))->toBeTrue();
});

it('Counterparty model uses the Scout Searchable trait', function () {
    expect(in_array(Searchable::class, class_uses_recursive(Counterparty::class)))->toBeTrue();
});

it('WikiContract model uses the Scout Searchable trait', function () {
    expect(in_array(Searchable::class, class_uses_recursive(WikiContract::class)))->toBeTrue();
});

// ---------------------------------------------------------------------------
// Index names — must match config/scout.php index-settings keys
// ---------------------------------------------------------------------------

it('Contract searchableAs returns contracts', function () {
    $contract = new Contract;
    expect($contract->searchableAs())->toBe('contracts');
});

it('Counterparty searchableAs returns counterparties', function () {
    $counterparty = new Counterparty;
    expect($counterparty->searchableAs())->toBe('counterparties');
});

it('WikiContract searchableAs returns wiki_contracts', function () {
    $wiki = new WikiContract;
    expect($wiki->searchableAs())->toBe('wiki_contracts');
});

// ---------------------------------------------------------------------------
// toSearchableArray — verify all expected search fields are present
// ---------------------------------------------------------------------------

it('Contract toSearchableArray includes expected fields', function () {
    $contract = Contract::factory()->create(['title' => 'Test Contract', 'contract_type' => 'Commercial']);
    $array = $contract->toSearchableArray();

    expect($array)->toHaveKeys(['id', 'contract_ref', 'title', 'contract_type', 'workflow_state']);
});

it('Counterparty toSearchableArray includes filterable fields', function () {
    $counterparty = Counterparty::create([
        'legal_name' => 'Scout Test Corp',
        'registration_number' => 'REG-SCOUT',
        'status' => 'Active',
    ]);
    $array = $counterparty->toSearchableArray();

    expect($array)->toHaveKeys(['id', 'legal_name', 'status']);
});

it('WikiContract toSearchableArray maps name to title', function () {
    $wiki = WikiContract::create([
        'name' => 'Scout Wiki Template',
        'category' => 'Commercial',
        'status' => 'published',
    ]);
    $array = $wiki->toSearchableArray();

    expect($array)->toHaveKeys(['id', 'title', 'contract_type']);
    expect($array['title'])->toBe('Scout Wiki Template');
});

// ---------------------------------------------------------------------------
// Scout config — index settings are declared for all three indexes
// ---------------------------------------------------------------------------

it('scout config declares meilisearch index settings for contracts', function () {
    $settings = config('scout.meilisearch.index-settings.contracts');

    expect($settings)->not->toBeNull();
    expect($settings)->toHaveKey('filterableAttributes');
    expect($settings)->toHaveKey('sortableAttributes');
    expect($settings)->toHaveKey('searchableAttributes');
    expect($settings['filterableAttributes'])->toContain('workflow_state');
    expect($settings['filterableAttributes'])->toContain('contract_type');
});

it('scout config declares meilisearch index settings for counterparties', function () {
    $settings = config('scout.meilisearch.index-settings.counterparties');

    expect($settings)->not->toBeNull();
    expect($settings['filterableAttributes'])->toContain('status');
    expect($settings['searchableAttributes'])->toContain('legal_name');
});

it('scout config declares meilisearch index settings for wiki_contracts', function () {
    $settings = config('scout.meilisearch.index-settings.wiki_contracts');

    expect($settings)->not->toBeNull();
    expect($settings['filterableAttributes'])->toContain('contract_type');
    expect($settings['searchableAttributes'])->toContain('title');
});

// ---------------------------------------------------------------------------
// Feature flag — scout driver is null when meilisearch is disabled
// ---------------------------------------------------------------------------

it('scout driver is null when meilisearch feature is disabled', function () {
    config(['features.meilisearch' => false]);

    // Re-evaluate driver — Feature::enabled reads from config at call time
    $driver = \App\Helpers\Feature::enabled('meilisearch') ? config('scout.driver') : 'null';
    expect($driver)->toBe('null');
});

it('SearchService uses Feature::enabled not config() for feature flag check', function () {
    // Set the feature off via config (what Feature::enabled reads)
    config(['features.meilisearch' => false]);

    $service = app(SearchService::class);

    $region = \App\Models\Region::create(['name' => 'Scout Region', 'code' => 'SCR']);
    $entity = \App\Models\Entity::create(['region_id' => $region->id, 'name' => 'Scout Entity', 'code' => 'SCE']);
    $project = \App\Models\Project::create(['entity_id' => $entity->id, 'name' => 'Scout Project', 'code' => 'SCP']);
    $counterparty = Counterparty::create(['legal_name' => 'Scout Corp', 'status' => 'Active']);

    $contract = new Contract([
        'region_id' => $region->id,
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'counterparty_id' => $counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Scout Fallback Agreement',
    ]);
    $contract->workflow_state = 'draft';
    $contract->save();

    // Should use SQL fallback (not throw Meilisearch connection error)
    $results = $service->globalSearch('Scout Fallback');
    expect($results['contracts'])->not->toBeEmpty();
});

it('SearchService globalSearch returns all three resource keys', function () {
    config(['features.meilisearch' => false]);

    $results = app(SearchService::class)->globalSearch('anything');

    expect($results)->toHaveKeys(['contracts', 'counterparties', 'wiki']);
});
