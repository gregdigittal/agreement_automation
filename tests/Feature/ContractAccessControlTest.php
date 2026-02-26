<?php

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\ContractResource\Pages\EditContract;
use App\Models\Contract;
use App\Models\ContractUserAccess;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');

    $this->legalUser = User::factory()->create();
    $this->legalUser->assignRole('legal');

    $this->commercialUser = User::factory()->create();
    $this->commercialUser->assignRole('commercial');

    $this->financeUser = User::factory()->create();
    $this->financeUser->assignRole('finance');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Test Entity', 'code' => 'TE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Test Project', 'code' => 'TP']);
    $this->counterparty = Counterparty::create(['legal_name' => 'Test Corp', 'status' => 'Active']);
});

function createContract(array $overrides = []): Contract
{
    $contract = Contract::create(array_merge([
        'region_id' => test()->region->id,
        'entity_id' => test()->entity->id,
        'project_id' => test()->project->id,
        'counterparty_id' => test()->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Test Contract',
        'is_restricted' => false,
    ], $overrides));
    $contract->workflow_state = 'draft';
    $contract->saveQuietly();
    return $contract;
}

// ── Migration tests ──────────────────────────────────────────────────────

it('has is_restricted column on contracts table', function () {
    $contract = createContract();
    expect($contract->is_restricted)->toBeFalse();
});

it('casts is_restricted to boolean', function () {
    $contract = createContract(['is_restricted' => true]);
    expect($contract->is_restricted)->toBeTrue()->toBeBool();
});

it('can create contract_user_access records', function () {
    $contract = createContract(['is_restricted' => true]);

    ContractUserAccess::create([
        'contract_id' => $contract->id,
        'user_id' => $this->legalUser->id,
        'access_level' => 'view',
        'granted_by' => $this->admin->id,
    ]);

    $this->assertDatabaseHas('contract_user_access', [
        'contract_id' => $contract->id,
        'user_id' => $this->legalUser->id,
        'access_level' => 'view',
    ]);
});

it('enforces unique constraint on contract_id + user_id', function () {
    $contract = createContract(['is_restricted' => true]);

    ContractUserAccess::create([
        'contract_id' => $contract->id,
        'user_id' => $this->legalUser->id,
        'access_level' => 'view',
    ]);

    expect(fn () => ContractUserAccess::create([
        'contract_id' => $contract->id,
        'user_id' => $this->legalUser->id,
        'access_level' => 'edit',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

// ── Model relationship tests ─────────────────────────────────────────────

it('contract has authorizedUsers relationship', function () {
    $contract = createContract(['is_restricted' => true]);
    $contract->authorizedUsers()->attach($this->legalUser->id, [
        'access_level' => 'edit',
        'granted_by' => $this->admin->id,
    ]);

    expect($contract->authorizedUsers)->toHaveCount(1);
    expect($contract->authorizedUsers->first()->id)->toBe($this->legalUser->id);
    expect($contract->authorizedUsers->first()->pivot->access_level)->toBe('edit');
});

it('contract has accessGrants relationship', function () {
    $contract = createContract(['is_restricted' => true]);
    ContractUserAccess::create([
        'contract_id' => $contract->id,
        'user_id' => $this->commercialUser->id,
        'access_level' => 'view',
    ]);

    expect($contract->accessGrants)->toHaveCount(1);
});

it('user has accessibleContracts relationship', function () {
    $contract = createContract(['is_restricted' => true]);
    $contract->authorizedUsers()->attach($this->commercialUser->id, ['access_level' => 'view']);

    expect($this->commercialUser->accessibleContracts)->toHaveCount(1);
    expect($this->commercialUser->accessibleContracts->first()->id)->toBe($contract->id);
});

it('cascades delete from contract to access grants', function () {
    $contract = createContract(['is_restricted' => true]);
    $contract->authorizedUsers()->attach($this->legalUser->id, ['access_level' => 'view']);

    $this->assertDatabaseHas('contract_user_access', ['contract_id' => $contract->id]);

    $contract->delete();

    $this->assertDatabaseMissing('contract_user_access', ['contract_id' => $contract->id]);
});

// ── Query filtering tests ────────────────────────────────────────────────

it('system admin sees all contracts including restricted', function () {
    $this->actingAs($this->admin);

    $unrestricted = createContract(['title' => 'Unrestricted']);
    $restricted = createContract(['title' => 'Restricted', 'is_restricted' => true]);

    $query = ContractResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->toContain($unrestricted->id);
    expect($ids)->toContain($restricted->id);
});

it('non-admin cannot see restricted contracts they are not authorized for', function () {
    $this->actingAs($this->commercialUser);

    $unrestricted = createContract(['title' => 'Unrestricted']);
    $restricted = createContract(['title' => 'Restricted', 'is_restricted' => true]);

    $query = ContractResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->toContain($unrestricted->id);
    expect($ids)->not->toContain($restricted->id);
});

it('authorized non-admin can see restricted contract', function () {
    $this->actingAs($this->legalUser);

    $restricted = createContract(['title' => 'Restricted', 'is_restricted' => true]);
    $restricted->authorizedUsers()->attach($this->legalUser->id, ['access_level' => 'view']);

    $query = ContractResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->toContain($restricted->id);
});

it('non-admin sees unrestricted contracts normally', function () {
    $this->actingAs($this->financeUser);

    $contract1 = createContract(['title' => 'Contract A']);
    $contract2 = createContract(['title' => 'Contract B']);

    $query = ContractResource::getEloquentQuery();
    expect($query->count())->toBe(2);
});

it('merchant agreement resource also filters restricted contracts', function () {
    $this->actingAs($this->commercialUser);

    $unrestricted = createContract([
        'title' => 'Open Merchant',
        'contract_type' => 'Merchant',
    ]);
    $restricted = createContract([
        'title' => 'Locked Merchant',
        'contract_type' => 'Merchant',
        'is_restricted' => true,
    ]);

    $query = \App\Filament\Resources\MerchantAgreementResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->toContain($unrestricted->id);
    expect($ids)->not->toContain($restricted->id);
});

// ── Filament form tests ──────────────────────────────────────────────────

it('access control section is visible to system admin', function () {
    $this->actingAs($this->admin);

    $contract = createContract();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->assertFormFieldExists('is_restricted');
});

it('access control section is visible to legal user', function () {
    $this->actingAs($this->legalUser);

    $contract = createContract();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->assertFormFieldExists('is_restricted');
});

it('admin can set contract as restricted', function () {
    $this->actingAs($this->admin);

    $contract = createContract();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm(['is_restricted' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contract->fresh()->is_restricted)->toBeTrue();
});

// ── Contract list visibility tests ───────────────────────────────────────

it('restricted contract is not in list for unauthorized user', function () {
    $restricted = createContract(['title' => 'Secret Contract', 'is_restricted' => true]);

    $this->actingAs($this->commercialUser);
    $response = $this->get('/admin/contracts');
    $response->assertSuccessful();
    $response->assertDontSee('Secret Contract');
});

it('restricted contract is in list for system admin', function () {
    $restricted = createContract(['title' => 'Admin Sees This', 'is_restricted' => true]);

    $this->actingAs($this->admin);
    $response = $this->get('/admin/contracts');
    $response->assertSuccessful();
    $response->assertSee('Admin Sees This');
});
