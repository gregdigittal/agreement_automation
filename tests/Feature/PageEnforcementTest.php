<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningSession;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    $this->region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Test Entity', 'code' => 'TE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Test Project', 'code' => 'TP']);
    $this->counterparty = Counterparty::create(['legal_name' => 'Test Corp', 'status' => 'Active']);
});

function createTestContract(array $overrides = []): Contract
{
    $contract = Contract::create(array_merge([
        'region_id' => test()->region->id,
        'entity_id' => test()->entity->id,
        'project_id' => test()->project->id,
        'counterparty_id' => test()->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Test Contract',
    ], $overrides));
    return $contract;
}

// ── Migration tests ─────────────────────────────────────────────────────

it('signing session has require_all_pages_viewed column', function () {
    $contract = createTestContract();

    $session = SigningSession::create([
        'contract_id' => $contract->id,
        'initiated_by' => $this->admin->id,
        'signing_order' => 'sequential',
        'status' => 'active',
        'document_hash' => hash('sha256', 'test'),
        'expires_at' => now()->addDays(30),
        'require_all_pages_viewed' => true,
    ]);

    expect($session->require_all_pages_viewed)->toBeTrue();
    $this->assertDatabaseHas('signing_sessions', [
        'id' => $session->id,
        'require_all_pages_viewed' => true,
    ]);
});

it('signing session has require_page_initials column', function () {
    $contract = createTestContract();

    $session = SigningSession::create([
        'contract_id' => $contract->id,
        'initiated_by' => $this->admin->id,
        'signing_order' => 'sequential',
        'status' => 'active',
        'document_hash' => hash('sha256', 'test'),
        'expires_at' => now()->addDays(30),
        'require_page_initials' => true,
    ]);

    expect($session->require_page_initials)->toBeTrue();
});

it('enforcement columns default to false', function () {
    $contract = createTestContract();

    $session = SigningSession::create([
        'contract_id' => $contract->id,
        'initiated_by' => $this->admin->id,
        'signing_order' => 'sequential',
        'status' => 'active',
        'document_hash' => hash('sha256', 'test'),
        'expires_at' => now()->addDays(30),
    ]);

    // Refresh from DB to pick up column defaults
    $session = $session->fresh();
    expect($session->require_all_pages_viewed)->toBeFalse();
    expect($session->require_page_initials)->toBeFalse();
});

it('casts enforcement columns to boolean', function () {
    $contract = createTestContract();

    $session = SigningSession::create([
        'contract_id' => $contract->id,
        'initiated_by' => $this->admin->id,
        'signing_order' => 'sequential',
        'status' => 'active',
        'document_hash' => hash('sha256', 'test'),
        'expires_at' => now()->addDays(30),
        'require_all_pages_viewed' => 1,
        'require_page_initials' => 0,
    ]);

    expect($session->require_all_pages_viewed)->toBeBool()->toBeTrue();
    expect($session->require_page_initials)->toBeBool()->toBeFalse();
});

it('can set both enforcement flags simultaneously', function () {
    $contract = createTestContract();

    $session = SigningSession::create([
        'contract_id' => $contract->id,
        'initiated_by' => $this->admin->id,
        'signing_order' => 'sequential',
        'status' => 'active',
        'document_hash' => hash('sha256', 'test'),
        'expires_at' => now()->addDays(30),
        'require_all_pages_viewed' => true,
        'require_page_initials' => true,
    ]);

    expect($session->require_all_pages_viewed)->toBeTrue();
    expect($session->require_page_initials)->toBeTrue();
});

it('can update enforcement flags on existing session', function () {
    $contract = createTestContract();

    $session = SigningSession::create([
        'contract_id' => $contract->id,
        'initiated_by' => $this->admin->id,
        'signing_order' => 'sequential',
        'status' => 'active',
        'document_hash' => hash('sha256', 'test'),
        'expires_at' => now()->addDays(30),
    ]);

    expect($session->fresh()->require_all_pages_viewed)->toBeFalse();

    $session->update(['require_all_pages_viewed' => true]);

    expect($session->fresh()->require_all_pages_viewed)->toBeTrue();
});

it('enforcement flags are included in fillable', function () {
    $fillable = (new SigningSession())->getFillable();

    expect($fillable)->toContain('require_all_pages_viewed');
    expect($fillable)->toContain('require_page_initials');
});

it('enforcement flags are in casts', function () {
    $casts = (new SigningSession())->getCasts();

    expect($casts)->toHaveKey('require_all_pages_viewed');
    expect($casts)->toHaveKey('require_page_initials');
    expect($casts['require_all_pages_viewed'])->toBe('boolean');
    expect($casts['require_page_initials'])->toBe('boolean');
});
