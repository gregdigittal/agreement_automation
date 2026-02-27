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

    $this->operationsUser = User::factory()->create();
    $this->operationsUser->assignRole('operations');

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'Restricted Region', 'code' => 'RR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Restricted Entity', 'code' => 'RE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Restricted Project', 'code' => 'RP']);
    $this->counterparty = Counterparty::create(['legal_name' => 'Restricted Corp', 'status' => 'Active']);
});

function createRestrictedContract(array $overrides = []): Contract
{
    $contract = Contract::create(array_merge([
        'region_id' => test()->region->id,
        'entity_id' => test()->entity->id,
        'project_id' => test()->project->id,
        'counterparty_id' => test()->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Restricted Test Contract',
        'is_restricted' => false,
    ], $overrides));
    $contract->workflow_state = 'draft';
    $contract->saveQuietly();
    return $contract;
}

// ── Restricting Contracts ───────────────────────────────────────────────────

it('system_admin can restrict a contract', function () {
    $this->actingAs($this->admin);

    $contract = createRestrictedContract();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm(['is_restricted' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contract->fresh()->is_restricted)->toBeTrue();
});

it('legal user can restrict a contract', function () {
    $this->actingAs($this->legalUser);

    $contract = createRestrictedContract();

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm(['is_restricted' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contract->fresh()->is_restricted)->toBeTrue();
});

it('commercial user cannot see the access control section', function () {
    $this->actingAs($this->commercialUser);

    // The access control section is only visible to system_admin and legal roles
    $canSeeAccessControl = $this->commercialUser->hasAnyRole(['system_admin', 'legal']);
    expect($canSeeAccessControl)->toBeFalse();
});

// ── Access Enforcement ──────────────────────────────────────────────────────

it('restricted contract is hidden from non-authorized users', function () {
    $this->actingAs($this->commercialUser);

    $restricted = createRestrictedContract([
        'title' => 'Hidden Contract',
        'is_restricted' => true,
    ]);

    $query = ContractResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->not->toContain($restricted->id);
});

it('unrestricted contract is visible to all authenticated users', function () {
    $this->actingAs($this->commercialUser);

    $unrestricted = createRestrictedContract([
        'title' => 'Visible Contract',
        'is_restricted' => false,
    ]);

    $query = ContractResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->toContain($unrestricted->id);
});

it('authorized user can see restricted contract', function () {
    $this->actingAs($this->legalUser);

    $restricted = createRestrictedContract([
        'title' => 'Authorized Access Contract',
        'is_restricted' => true,
    ]);

    $restricted->authorizedUsers()->attach($this->legalUser->id, ['access_level' => 'view']);

    $query = ContractResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->toContain($restricted->id);
});

it('unauthorized user cannot see restricted contract even with other roles', function () {
    $this->actingAs($this->financeUser);

    $restricted = createRestrictedContract([
        'title' => 'Finance Blocked Contract',
        'is_restricted' => true,
    ]);

    $query = ContractResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->not->toContain($restricted->id);
});

it('system_admin always has access to restricted contracts', function () {
    $this->actingAs($this->admin);

    $restricted = createRestrictedContract([
        'title' => 'Admin Always Sees',
        'is_restricted' => true,
    ]);

    $query = ContractResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->toContain($restricted->id);
});

// ── Managing Authorized Users ───────────────────────────────────────────────

it('authorized users can be added to restricted contract', function () {
    $contract = createRestrictedContract(['is_restricted' => true]);

    ContractUserAccess::create([
        'contract_id' => $contract->id,
        'user_id' => $this->commercialUser->id,
        'access_level' => 'view',
        'granted_by' => $this->admin->id,
    ]);

    $this->assertDatabaseHas('contract_user_access', [
        'contract_id' => $contract->id,
        'user_id' => $this->commercialUser->id,
        'access_level' => 'view',
    ]);
});

it('authorized user with access can see restricted contract in query', function () {
    $contract = createRestrictedContract(['is_restricted' => true]);

    $contract->authorizedUsers()->attach($this->commercialUser->id, [
        'access_level' => 'view',
        'granted_by' => $this->admin->id,
    ]);

    $this->actingAs($this->commercialUser);

    $query = ContractResource::getEloquentQuery();
    $ids = $query->pluck('id')->toArray();

    expect($ids)->toContain($contract->id);
});

it('removing authorized user access hides contract again', function () {
    $contract = createRestrictedContract(['is_restricted' => true]);

    $contract->authorizedUsers()->attach($this->commercialUser->id, [
        'access_level' => 'view',
        'granted_by' => $this->admin->id,
    ]);

    // Verify access works
    $this->actingAs($this->commercialUser);
    $ids = ContractResource::getEloquentQuery()->pluck('id')->toArray();
    expect($ids)->toContain($contract->id);

    // Remove access
    ContractUserAccess::where('contract_id', $contract->id)
        ->where('user_id', $this->commercialUser->id)
        ->delete();

    // Verify access is revoked
    $ids = ContractResource::getEloquentQuery()->pluck('id')->toArray();
    expect($ids)->not->toContain($contract->id);
});

it('unique constraint on contract_id and user_id prevents duplicate access grants', function () {
    $contract = createRestrictedContract(['is_restricted' => true]);

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

// ── Unrestricting ───────────────────────────────────────────────────────────

it('unrestricting a contract makes it visible to all users', function () {
    $this->actingAs($this->admin);

    $contract = createRestrictedContract([
        'title' => 'Was Restricted Contract',
        'is_restricted' => true,
    ]);

    // Verify it's hidden from commercial user
    $this->actingAs($this->commercialUser);
    $ids = ContractResource::getEloquentQuery()->pluck('id')->toArray();
    expect($ids)->not->toContain($contract->id);

    // Admin unrestricts the contract
    $this->actingAs($this->admin);
    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm(['is_restricted' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contract->fresh()->is_restricted)->toBeFalse();

    // Now commercial user can see it
    $this->actingAs($this->commercialUser);
    $ids = ContractResource::getEloquentQuery()->pluck('id')->toArray();
    expect($ids)->toContain($contract->id);
});
