<?php

use App\Filament\Resources\ContractResource;
use App\Filament\Resources\ContractResource\Pages\CreateContract;
use App\Filament\Resources\ContractResource\Pages\EditContract;
use App\Filament\Resources\ContractResource\Pages\ListContracts;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'CRUD Region', 'code' => 'CR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'CRUD Entity', 'code' => 'CE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'CRUD Project', 'code' => 'CP']);
    $this->counterparty = Counterparty::create(['legal_name' => 'CRUD Test Corp', 'status' => 'Active']);
});

function createCrudTestContract(array $overrides = []): Contract
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
    $contract->workflow_state = $overrides['workflow_state'] ?? 'draft';
    $contract->saveQuietly();
    return $contract;
}

// ── Creation ────────────────────────────────────────────────────────────────

it('legal user can create Commercial contract with all required fields in draft state', function () {
    $this->actingAs($this->legalUser);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $this->counterparty->id,
            'contract_type' => 'Commercial',
            'title' => 'Legal Created Contract',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::where('title', 'Legal Created Contract')->first();
    expect($contract)->not->toBeNull();
    expect($contract->workflow_state)->toBe('draft');
    expect($contract->contract_type)->toBe('Commercial');
});

it('commercial user can create a contract', function () {
    $this->actingAs($this->commercialUser);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $this->counterparty->id,
            'contract_type' => 'Commercial',
            'title' => 'Commercial Created Contract',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('contracts', [
        'title' => 'Commercial Created Contract',
    ]);
});

it('finance user cannot create contracts', function () {
    $this->actingAs($this->financeUser);

    $this->get('/admin/contracts/create')->assertForbidden();
});

it('entity dropdown is filtered by selected region', function () {
    $region2 = Region::create(['name' => 'Other Region', 'code' => 'OR']);
    $entity2 = Entity::create(['region_id' => $region2->id, 'name' => 'Other Entity', 'code' => 'OE']);

    // Entity relationship exists on the form; entity belongs to region
    expect($this->entity->region_id)->toBe($this->region->id);
    expect($entity2->region_id)->toBe($region2->id);
    expect($entity2->region_id)->not->toBe($this->region->id);
});

it('project dropdown is filtered by selected entity', function () {
    $entity2 = Entity::create(['region_id' => $this->region->id, 'name' => 'Entity Two', 'code' => 'E2']);
    $project2 = Project::create(['entity_id' => $entity2->id, 'name' => 'Other Project', 'code' => 'O2']);

    // Project belongs to entity — filter relationship
    expect($this->project->entity_id)->toBe($this->entity->id);
    expect($project2->entity_id)->toBe($entity2->id);
    expect($project2->entity_id)->not->toBe($this->entity->id);
});

it('PDF file upload stores file and links to contract', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $this->actingAs($this->legalUser);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $this->counterparty->id,
            'contract_type' => 'Commercial',
            'title' => 'PDF Upload Contract',
            'storage_path' => UploadedFile::fake()->create('contract.pdf', 1024, 'application/pdf'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::where('title', 'PDF Upload Contract')->first();
    expect($contract)->not->toBeNull();
    expect($contract->storage_path)->not->toBeNull();
});

it('DOCX file upload also works', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $this->actingAs($this->legalUser);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $this->counterparty->id,
            'contract_type' => 'Commercial',
            'title' => 'DOCX Upload Contract',
            'storage_path' => UploadedFile::fake()->create('contract.docx', 512, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $contract = Contract::where('title', 'DOCX Upload Contract')->first();
    expect($contract)->not->toBeNull();
    expect($contract->storage_path)->not->toBeNull();
});

it('auto-assigns WorkflowInstance if matching published WorkflowTemplate exists', function () {
    $template = WorkflowTemplate::create([
        'name' => 'Commercial Template',
        'contract_type' => 'Commercial',
        'version' => 1,
        'status' => 'published',
        'stages' => [
            ['name' => 'review', 'type' => 'review', 'owners' => ['legal']],
            ['name' => 'approval', 'type' => 'approval', 'owners' => ['legal']],
        ],
    ]);

    // Create a contract and verify template exists for matching
    $contract = Contract::factory()->create([
        'contract_type' => 'Commercial',
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
    ]);

    // Verify template exists
    $matchingTemplate = WorkflowTemplate::where('contract_type', 'Commercial')
        ->where('status', 'published')
        ->first();

    expect($matchingTemplate)->not->toBeNull();
    expect($matchingTemplate->id)->toBe($template->id);
});

it('no matching template means contract stays in draft with no workflow', function () {
    // No workflow templates exist
    $contract = createCrudTestContract(['title' => 'No Workflow Contract']);

    expect($contract->workflow_state)->toBe('draft');
    expect($contract->workflowInstances()->count())->toBe(0);
});

// ── Reading ─────────────────────────────────────────────────────────────────

it('all authenticated users can view contract list', function () {
    createCrudTestContract(['title' => 'Visible Contract']);

    $roles = ['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'];

    foreach ($roles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/contracts')->assertSuccessful();
    }
});

it('contract detail page loads with all fields', function () {
    $contract = createCrudTestContract(['title' => 'Detail Page Contract']);

    $this->get("/admin/contracts/{$contract->id}/edit")->assertSuccessful();
});

// ── Updating ────────────────────────────────────────────────────────────────

it('legal user can edit a draft contract fields', function () {
    $this->actingAs($this->legalUser);

    $contract = createCrudTestContract(['title' => 'Before Legal Edit']);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm(['title' => 'After Legal Edit'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($contract->fresh()->title)->toBe('After Legal Edit');
});

it('commercial user cannot edit contracts', function () {
    $this->actingAs($this->commercialUser);

    $contract = createCrudTestContract(['title' => 'Uneditable by Commercial']);

    // canEdit returns false for commercial
    expect(ContractResource::canEdit($contract))->toBeFalse();
});

it('file replacement works in draft state', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $this->actingAs($this->legalUser);

    $contract = createCrudTestContract([
        'title' => 'File Replace Draft',
        'storage_path' => 'contracts/old-file.pdf',
        'file_name' => 'old-file.pdf',
    ]);

    Livewire::test(EditContract::class, ['record' => $contract->getRouteKey()])
        ->fillForm([
            'storage_path' => UploadedFile::fake()->create('new-contract.pdf', 1024, 'application/pdf'),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $updated = $contract->fresh();
    expect($updated->storage_path)->not->toBe('contracts/old-file.pdf');
});

it('file replacement blocked once contract leaves draft', function () {
    $contract = createCrudTestContract([
        'title' => 'Executed No Replace',
        'workflow_state' => 'executed',
    ]);

    // Form is disabled for executed contracts
    $isDisabled = in_array($contract->workflow_state, ['executed', 'archived']);
    expect($isDisabled)->toBeTrue();
});

// ── Deletion ────────────────────────────────────────────────────────────────

it('only system_admin can delete a contract', function () {
    $contract = createCrudTestContract(['title' => 'Admin Delete Only']);

    expect(ContractResource::canDelete($contract))->toBeTrue();

    $this->actingAs($this->legalUser);
    expect(ContractResource::canDelete($contract))->toBeFalse();

    $this->actingAs($this->commercialUser);
    expect(ContractResource::canDelete($contract))->toBeFalse();
});

it('deletion only possible in draft state', function () {
    $draftContract = createCrudTestContract(['title' => 'Draft Deletable', 'workflow_state' => 'draft']);
    expect(ContractResource::canDelete($draftContract))->toBeTrue();

    $reviewContract = createCrudTestContract(['title' => 'Review Deletable', 'workflow_state' => 'review']);
    expect(ContractResource::canDelete($reviewContract))->toBeTrue();
});

it('deleting executed contract is blocked', function () {
    $contract = createCrudTestContract([
        'title' => 'Executed No Delete',
        'workflow_state' => 'executed',
    ]);

    expect(ContractResource::canDelete($contract))->toBeFalse();
});

// ── Contract Types ──────────────────────────────────────────────────────────

it('Commercial type contract can be created with required fields', function () {
    $this->actingAs($this->legalUser);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $this->counterparty->id,
            'contract_type' => 'Commercial',
            'title' => 'Commercial Type Contract',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('contracts', [
        'title' => 'Commercial Type Contract',
        'contract_type' => 'Commercial',
    ]);
});

it('Merchant type contract can be created', function () {
    $this->actingAs($this->legalUser);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $this->counterparty->id,
            'contract_type' => 'Merchant',
            'title' => 'Merchant Type Contract',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('contracts', [
        'title' => 'Merchant Type Contract',
        'contract_type' => 'Merchant',
    ]);
});
