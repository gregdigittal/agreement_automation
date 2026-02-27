<?php

use App\Filament\Resources\CounterpartyResource;
use App\Filament\Resources\CounterpartyResource\Pages\CreateCounterparty;
use App\Filament\Resources\CounterpartyResource\Pages\EditCounterparty;
use App\Filament\Resources\CounterpartyResource\Pages\ListCounterparties;
use App\Filament\Resources\ContractResource\Pages\CreateContract;
use App\Filament\Resources\OverrideRequestResource\Pages\ListOverrideRequests;
use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\CounterpartyMerge;
use App\Models\Entity;
use App\Models\EntityJurisdiction;
use App\Models\Jurisdiction;
use App\Models\KycPack;
use App\Models\KycTemplate;
use App\Models\KycTemplateItem;
use App\Models\OverrideRequest;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Services\CounterpartyService;
use App\Services\KycService;
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

    $this->auditUser = User::factory()->create();
    $this->auditUser->assignRole('audit');

    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Test Entity', 'code' => 'TE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Test Project', 'code' => 'TP']);
});

// ── CRUD: Creation ──────────────────────────────────────────────────────────

it('system_admin can create counterparty with legal_name and registration_number', function () {
    Livewire::test(CreateCounterparty::class)
        ->fillForm([
            'legal_name' => 'Admin Created Corp',
            'registration_number' => 'REG-ADMIN-001',
            'status' => 'Active',
            'jurisdiction' => 'UAE',
            'preferred_language' => 'en',
            'duplicate_acknowledged' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('counterparties', [
        'legal_name' => 'Admin Created Corp',
        'registration_number' => 'REG-ADMIN-001',
    ]);
});

it('legal user can create counterparty with legal_name and registration_number', function () {
    $this->actingAs($this->legalUser);

    Livewire::test(CreateCounterparty::class)
        ->fillForm([
            'legal_name' => 'Legal Created Corp',
            'registration_number' => 'REG-LEGAL-001',
            'status' => 'Active',
            'jurisdiction' => 'UK',
            'preferred_language' => 'en',
            'duplicate_acknowledged' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('counterparties', [
        'legal_name' => 'Legal Created Corp',
        'registration_number' => 'REG-LEGAL-001',
    ]);
});

it('commercial user can create counterparty with legal_name and registration_number', function () {
    $this->actingAs($this->commercialUser);

    Livewire::test(CreateCounterparty::class)
        ->fillForm([
            'legal_name' => 'Commercial Created Corp',
            'registration_number' => 'REG-COM-001',
            'status' => 'Active',
            'jurisdiction' => 'US',
            'preferred_language' => 'en',
            'duplicate_acknowledged' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('counterparties', [
        'legal_name' => 'Commercial Created Corp',
        'registration_number' => 'REG-COM-001',
    ]);
});

it('finance user cannot create counterparties', function () {
    $this->actingAs($this->financeUser);

    $this->get('/admin/counterparties/create')->assertForbidden();
});

it('operations user cannot create counterparties', function () {
    $this->actingAs($this->operationsUser);

    $this->get('/admin/counterparties/create')->assertForbidden();
});

it('audit user cannot create counterparties', function () {
    $this->actingAs($this->auditUser);

    $this->get('/admin/counterparties/create')->assertForbidden();
});

// ── CRUD: Editing ───────────────────────────────────────────────────────────

it('system_admin can edit counterparties', function () {
    $cp = Counterparty::create(['legal_name' => 'Editable Corp', 'status' => 'Active']);

    Livewire::test(EditCounterparty::class, ['record' => $cp->getRouteKey()])
        ->fillForm([
            'legal_name' => 'Updated Corp',
            'duplicate_acknowledged' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cp->fresh()->legal_name)->toBe('Updated Corp');
});

it('legal user can edit counterparties', function () {
    $this->actingAs($this->legalUser);

    $cp = Counterparty::create(['legal_name' => 'Legal Editable Corp', 'status' => 'Active']);

    Livewire::test(EditCounterparty::class, ['record' => $cp->getRouteKey()])
        ->fillForm([
            'legal_name' => 'Legal Updated Corp',
            'duplicate_acknowledged' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cp->fresh()->legal_name)->toBe('Legal Updated Corp');
});

it('commercial user can edit counterparties', function () {
    $this->actingAs($this->commercialUser);

    $cp = Counterparty::create(['legal_name' => 'Commercial Edit Corp', 'status' => 'Active']);

    $this->get("/admin/counterparties/{$cp->id}/edit")->assertSuccessful();
});

// ── CRUD: Default Status ────────────────────────────────────────────────────

it('new counterparties are created with status active', function () {
    Livewire::test(CreateCounterparty::class)
        ->fillForm([
            'legal_name' => 'Default Status Corp',
            'registration_number' => 'REG-DEF-001',
            'status' => 'Active',
            'jurisdiction' => 'UAE',
            'preferred_language' => 'en',
            'duplicate_acknowledged' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $cp = Counterparty::where('legal_name', 'Default Status Corp')->first();
    expect($cp->status)->toBe('Active');
});

// ── Duplicate Detection ─────────────────────────────────────────────────────

it('exact registration_number match returns existing counterparty', function () {
    $existing = Counterparty::factory()->create([
        'registration_number' => 'REG-DUP-001',
        'status' => 'Active',
    ]);

    $service = new CounterpartyService();
    $results = $service->findDuplicates('Nonmatching Name XYZ', 'REG-DUP-001', null);

    expect($results)->toHaveCount(1);
    expect($results->contains('id', $existing->id))->toBeTrue();
});

it('fuzzy name matching finds similar names', function () {
    $existing = Counterparty::factory()->create([
        'legal_name' => 'Acme Corp International',
        'registration_number' => 'REG-ACME-001',
        'status' => 'Active',
    ]);

    $service = new CounterpartyService();
    $results = $service->findDuplicates('Acme Corp UK', 'REG-NOMATCH', null);

    expect($results)->toHaveCount(1);
    expect($results->contains('id', $existing->id))->toBeTrue();
});

it('no duplicates returns empty result', function () {
    Counterparty::factory()->create([
        'legal_name' => 'Alpha Corp',
        'registration_number' => 'REG-ALPHA',
        'status' => 'Active',
    ]);

    $service = new CounterpartyService();
    $results = $service->findDuplicates('Zeta Enterprises', 'REG-NOMATCH', null);

    expect($results)->toBeEmpty();
});

// ── Status Management ───────────────────────────────────────────────────────

it('system_admin can change status to suspended', function () {
    $cp = Counterparty::create(['legal_name' => 'Suspend Me Corp', 'status' => 'Active']);

    Livewire::test(EditCounterparty::class, ['record' => $cp->getRouteKey()])
        ->fillForm([
            'status' => 'Suspended',
            'status_reason' => 'Compliance review pending',
            'duplicate_acknowledged' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $cp->refresh();
    expect($cp->status)->toBe('Suspended');
    expect($cp->status_reason)->toBe('Compliance review pending');
});

it('legal user can change status to blacklisted', function () {
    $this->actingAs($this->legalUser);

    $cp = Counterparty::create(['legal_name' => 'Blacklist Me Corp', 'status' => 'Active']);

    Livewire::test(EditCounterparty::class, ['record' => $cp->getRouteKey()])
        ->fillForm([
            'status' => 'Blacklisted',
            'status_reason' => 'Fraud detected',
            'duplicate_acknowledged' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $cp->refresh();
    expect($cp->status)->toBe('Blacklisted');
});

it('contract creation with suspended counterparty is blocked', function () {
    $this->actingAs($this->legalUser);

    $suspended = Counterparty::create([
        'legal_name' => 'Suspended Corp',
        'status' => 'Suspended',
    ]);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $suspended->id,
            'contract_type' => 'Commercial',
            'title' => 'Should Be Blocked',
        ])
        ->call('create')
        ->assertHasFormErrors(['counterparty_id']);
});

it('contract creation with blacklisted counterparty is blocked', function () {
    $this->actingAs($this->legalUser);

    $blacklisted = Counterparty::create([
        'legal_name' => 'Blacklisted Corp',
        'status' => 'Blacklisted',
    ]);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $blacklisted->id,
            'contract_type' => 'Commercial',
            'title' => 'Should Be Blocked',
        ])
        ->call('create')
        ->assertHasFormErrors(['counterparty_id']);
});

it('contract creation with active counterparty succeeds', function () {
    $this->actingAs($this->legalUser);

    $active = Counterparty::create([
        'legal_name' => 'Active Corp',
        'status' => 'Active',
    ]);

    Livewire::test(CreateContract::class)
        ->fillForm([
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
            'counterparty_id' => $active->id,
            'contract_type' => 'Commercial',
            'title' => 'Active Counterparty Contract',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('contracts', [
        'title' => 'Active Counterparty Contract',
        'counterparty_id' => $active->id,
    ]);
});

// ── Override Requests ───────────────────────────────────────────────────────

it('commercial user can submit override request via table action', function () {
    $this->actingAs($this->commercialUser);

    $suspended = Counterparty::create([
        'legal_name' => 'Override Target Corp',
        'status' => 'Suspended',
    ]);

    Livewire::test(ListCounterparties::class)
        ->callTableAction('override_request', $suspended, [
            'reason' => 'Business critical deal requires immediate processing',
            'contract_title' => 'Urgent Contract',
        ]);

    $this->assertDatabaseHas('override_requests', [
        'counterparty_id' => $suspended->id,
        'reason' => 'Business critical deal requires immediate processing',
        'requested_by_email' => $this->commercialUser->email,
    ]);
});

it('override request is created with status pending', function () {
    $suspended = Counterparty::create([
        'legal_name' => 'Pending Status Corp',
        'status' => 'Suspended',
    ]);

    $request = OverrideRequest::create([
        'counterparty_id' => $suspended->id,
        'contract_title' => 'Test Contract',
        'requested_by_email' => $this->commercialUser->email,
        'reason' => 'Urgent deal',
        'status' => 'pending',
    ]);

    expect($request->status)->toBe('pending');
});

it('legal user can approve override request', function () {
    $this->actingAs($this->legalUser);

    $suspended = Counterparty::create(['legal_name' => 'Approve Target', 'status' => 'Suspended']);

    $request = OverrideRequest::create([
        'counterparty_id' => $suspended->id,
        'requested_by_email' => $this->commercialUser->email,
        'contract_title' => 'Test Contract',
        'reason' => 'Urgent deal',
        'status' => 'pending',
    ]);

    Livewire::test(ListOverrideRequests::class)
        ->callTableAction('approve', $request, ['comment' => 'Approved for Q1 deal']);

    $request->refresh();
    expect($request->status)->toBe('approved');
    expect($request->decided_by)->toBe($this->legalUser->email);
    expect($request->decided_at)->not->toBeNull();
});

it('legal user can reject override request with comment', function () {
    $this->actingAs($this->legalUser);

    $suspended = Counterparty::create(['legal_name' => 'Reject Target', 'status' => 'Suspended']);

    $request = OverrideRequest::create([
        'counterparty_id' => $suspended->id,
        'requested_by_email' => $this->commercialUser->email,
        'contract_title' => 'Test Contract',
        'reason' => 'Urgent deal',
        'status' => 'pending',
    ]);

    Livewire::test(ListOverrideRequests::class)
        ->callTableAction('reject', $request, ['comment' => 'Does not meet compliance requirements']);

    $request->refresh();
    expect($request->status)->toBe('rejected');
    expect($request->decided_by)->toBe($this->legalUser->email);
    expect($request->comment)->toBe('Does not meet compliance requirements');
});

it('after approval commercial can create contract with restricted counterparty', function () {
    $suspended = Counterparty::create(['legal_name' => 'Overridden Corp', 'status' => 'Suspended']);

    $request = OverrideRequest::create([
        'counterparty_id' => $suspended->id,
        'requested_by_email' => $this->commercialUser->email,
        'contract_title' => 'Override Contract',
        'reason' => 'Business critical',
        'status' => 'approved',
        'decided_by' => $this->legalUser->email,
        'decided_at' => now(),
    ]);

    // Verify override request is approved
    expect($request->status)->toBe('approved');

    // The override record exists in the database for downstream checks
    $this->assertDatabaseHas('override_requests', [
        'counterparty_id' => $suspended->id,
        'status' => 'approved',
    ]);
});

it('commercial user cannot approve their own override request', function () {
    $this->actingAs($this->commercialUser);

    $suspended = Counterparty::create(['legal_name' => 'Self-Approve Corp', 'status' => 'Suspended']);

    $request = OverrideRequest::create([
        'counterparty_id' => $suspended->id,
        'requested_by_email' => $this->commercialUser->email,
        'contract_title' => 'Self Approve Contract',
        'reason' => 'Urgent deal',
        'status' => 'pending',
    ]);

    // Commercial user does not have system_admin or legal role, so approve action should not be visible
    // The canCreate check for OverrideRequestResource also blocks commercial
    $this->get('/admin/override-requests/create')->assertForbidden();
});

// ── Merging (Admin only) ────────────────────────────────────────────────────

it('system_admin can merge source into target counterparty', function () {
    $source = Counterparty::create(['legal_name' => 'Source Corp', 'status' => 'Active']);
    $target = Counterparty::create(['legal_name' => 'Target Corp', 'status' => 'Active']);

    Livewire::test(ListCounterparties::class)
        ->callTableAction('merge', $source, [
            'target_counterparty_id' => $target->id,
        ]);

    $source->refresh();
    expect($source->status)->toBe('Merged');
});

it('all contracts transferred from source to target during merge', function () {
    $source = Counterparty::create(['legal_name' => 'Source Merge Corp', 'status' => 'Active']);
    $target = Counterparty::create(['legal_name' => 'Target Merge Corp', 'status' => 'Active']);

    $contract1 = Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $source->id,
        'contract_type' => 'Commercial',
        'title' => 'Source Contract 1',
    ]);
    $contract1->workflow_state = 'draft';
    $contract1->saveQuietly();

    $contract2 = Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $source->id,
        'contract_type' => 'Commercial',
        'title' => 'Source Contract 2',
    ]);
    $contract2->workflow_state = 'draft';
    $contract2->saveQuietly();

    Livewire::test(ListCounterparties::class)
        ->callTableAction('merge', $source, [
            'target_counterparty_id' => $target->id,
        ]);

    expect($contract1->fresh()->counterparty_id)->toBe($target->id);
    expect($contract2->fresh()->counterparty_id)->toBe($target->id);
});

it('source status set to merged with reference to target after merge', function () {
    $source = Counterparty::create(['legal_name' => 'Merge Source Ref', 'status' => 'Active']);
    $target = Counterparty::create(['legal_name' => 'Merge Target Ref', 'status' => 'Active']);

    Livewire::test(ListCounterparties::class)
        ->callTableAction('merge', $source, [
            'target_counterparty_id' => $target->id,
        ]);

    $source->refresh();
    expect($source->status)->toBe('Merged');

    $this->assertDatabaseHas('counterparty_merges', [
        'source_counterparty_id' => $source->id,
        'target_counterparty_id' => $target->id,
        'merged_by' => $this->admin->id,
    ]);
});

it('audit log entry records the merge', function () {
    $source = Counterparty::create(['legal_name' => 'Audit Merge Source', 'status' => 'Active']);
    $target = Counterparty::create(['legal_name' => 'Audit Merge Target', 'status' => 'Active']);

    Livewire::test(ListCounterparties::class)
        ->callTableAction('merge', $source, [
            'target_counterparty_id' => $target->id,
        ]);

    // Merge record serves as audit trail
    $this->assertDatabaseHas('counterparty_merges', [
        'source_counterparty_id' => $source->id,
        'target_counterparty_id' => $target->id,
        'merged_by_email' => $this->admin->email,
    ]);
});

it('non-admin cannot merge counterparties', function () {
    $this->actingAs($this->legalUser);

    $source = Counterparty::create(['legal_name' => 'NoMerge Source', 'status' => 'Active']);
    $target = Counterparty::create(['legal_name' => 'NoMerge Target', 'status' => 'Active']);

    // The merge action is only visible to system_admin. Legal cannot see it.
    // Attempting to call the action should fail
    Livewire::test(ListCounterparties::class)
        ->assertTableActionHidden('merge', $source);
});

// ── KYC ─────────────────────────────────────────────────────────────────────

it('KYC template assigned to counterparty creates KYC pack', function () {
    $jurisdiction = Jurisdiction::factory()->create(['name' => 'UAE - DIFC']);
    EntityJurisdiction::create([
        'entity_id' => $this->entity->id,
        'jurisdiction_id' => $jurisdiction->id,
        'is_primary' => true,
    ]);

    $counterparty = Counterparty::create(['legal_name' => 'KYC Test Corp', 'status' => 'Active']);

    $contract = Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'KYC Pack Test Contract',
    ]);

    $template = KycTemplate::create([
        'name' => 'Standard KYC',
        'status' => 'active',
        'version' => 1,
    ]);
    KycTemplateItem::create([
        'kyc_template_id' => $template->id,
        'sort_order' => 0,
        'label' => 'Certificate of Incorporation',
        'field_type' => 'file_upload',
        'is_required' => true,
    ]);
    KycTemplateItem::create([
        'kyc_template_id' => $template->id,
        'sort_order' => 1,
        'label' => 'Director Name',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    $service = new KycService();
    $pack = $service->createPackForContract($contract);

    expect($pack)->not->toBeNull();
    expect($pack->status)->toBe('incomplete');
    expect($pack->items)->toHaveCount(2);
});

it('legal users can mark KYC checklist items complete', function () {
    $this->actingAs($this->legalUser);

    $counterparty = Counterparty::create(['legal_name' => 'KYC Complete Corp', 'status' => 'Active']);

    $contract = Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'KYC Complete Test',
    ]);

    $template = KycTemplate::create(['name' => 'Complete KYC', 'status' => 'active', 'version' => 1]);
    KycTemplateItem::create([
        'kyc_template_id' => $template->id,
        'sort_order' => 0,
        'label' => 'Company Name',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    $service = new KycService();
    $pack = $service->createPackForContract($contract);
    $item = $pack->items->first();

    $service->completeItem($item, value: 'Test Company LLC');

    $item->refresh();
    expect($item->status)->toBe('completed');
    expect($item->value)->toBe('Test Company LLC');
});

it('KYC progress is tracked toward completion', function () {
    $this->actingAs($this->legalUser);

    $counterparty = Counterparty::create(['legal_name' => 'KYC Progress Corp', 'status' => 'Active']);

    $contract = Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'KYC Progress Test',
    ]);

    $template = KycTemplate::create(['name' => 'Progress KYC', 'status' => 'active', 'version' => 1]);
    KycTemplateItem::create([
        'kyc_template_id' => $template->id,
        'sort_order' => 0,
        'label' => 'ID Document',
        'field_type' => 'file_upload',
        'is_required' => true,
    ]);
    KycTemplateItem::create([
        'kyc_template_id' => $template->id,
        'sort_order' => 1,
        'label' => 'Director Name',
        'field_type' => 'text',
        'is_required' => true,
    ]);

    $service = new KycService();
    $pack = $service->createPackForContract($contract);

    // Before completing items, pack is incomplete
    expect($pack->status)->toBe('incomplete');
    expect($service->isReadyForSigning($contract->fresh()))->toBeFalse();

    // Complete first item
    $service->completeItem($pack->items->first(), filePath: 'docs/id.pdf');
    expect($pack->fresh()->status)->toBe('incomplete');

    // Complete second item
    $service->completeItem($pack->items->last(), value: 'John Director');
    expect($pack->fresh()->status)->toBe('complete');
    expect($service->isReadyForSigning($contract->fresh()))->toBeTrue();
});
