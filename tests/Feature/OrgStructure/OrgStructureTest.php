<?php

use App\Filament\Resources\EntityResource\Pages\CreateEntity;
use App\Filament\Resources\EntityResource\Pages\EditEntity;
use App\Filament\Resources\JurisdictionResource\Pages\CreateJurisdiction;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Resources\RegionResource\Pages\CreateRegion;
use App\Filament\Resources\RegionResource\Pages\EditRegion;
use App\Filament\Resources\SigningAuthorityResource\Pages\CreateSigningAuthority;
use App\Models\Entity;
use App\Models\Jurisdiction;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningAuthority;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ══════════════════════════════════════════════════════════════════════════════
// ACCESS CONTROL
// ══════════════════════════════════════════════════════════════════════════════

// ── 1. Only system_admin can create/edit/delete Regions, Entities, Projects, Jurisdictions, Signing Authorities ─

it('only system_admin can access create pages for org structure resources', function () {
    // system_admin can access all create pages
    $this->get('/admin/regions/create')->assertSuccessful();
    $this->get('/admin/entities/create')->assertSuccessful();
    $this->get('/admin/projects/create')->assertSuccessful();
    $this->get('/admin/jurisdictions/create')->assertSuccessful();
    $this->get('/admin/signing-authorities/create')->assertSuccessful();
});

it('non-admin roles cannot create regions', function () {
    foreach (['legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/regions/create')->assertForbidden();
    }
});

it('non-admin roles cannot create entities', function () {
    foreach (['legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/entities/create')->assertForbidden();
    }
});

it('non-admin roles cannot create projects', function () {
    foreach (['legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/projects/create')->assertForbidden();
    }
});

it('non-admin roles cannot create jurisdictions', function () {
    foreach (['legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/jurisdictions/create')->assertForbidden();
    }
});

it('non-admin roles cannot create signing authorities', function () {
    foreach (['legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/signing-authorities/create')->assertForbidden();
    }
});

it('non-admin roles cannot edit a region', function () {
    $region = Region::create(['name' => 'Test Region', 'code' => 'AE']);

    foreach (['legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        expect(\App\Filament\Resources\RegionResource::canEdit($region))->toBeFalse();
    }
});

it('non-admin roles cannot delete a region', function () {
    $region = Region::create(['name' => 'Test Region', 'code' => 'GB']);

    foreach (['legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        expect(\App\Filament\Resources\RegionResource::canDelete($region))->toBeFalse();
    }
});

it('system_admin can edit and delete org structure resources', function () {
    $region = Region::create(['name' => 'Editable Region', 'code' => 'US']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Editable Entity', 'code' => 'EE']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Editable Project', 'code' => 'EP']);
    $jurisdiction = Jurisdiction::create(['name' => 'Editable Jurisdiction', 'country_code' => 'XX', 'is_active' => true]);
    $user = User::factory()->create();
    $signingAuthority = SigningAuthority::create([
        'entity_id' => $entity->id,
        'user_id' => $user->id,
        'user_email' => 'signer@example.com',
        'role_or_name' => 'CFO',
    ]);

    expect(\App\Filament\Resources\RegionResource::canEdit($region))->toBeTrue();
    expect(\App\Filament\Resources\RegionResource::canDelete($region))->toBeTrue();
    expect(\App\Filament\Resources\EntityResource::canEdit($entity))->toBeTrue();
    expect(\App\Filament\Resources\EntityResource::canDelete($entity))->toBeTrue();
    expect(\App\Filament\Resources\ProjectResource::canEdit($project))->toBeTrue();
    expect(\App\Filament\Resources\ProjectResource::canDelete($project))->toBeTrue();
    expect(\App\Filament\Resources\JurisdictionResource::canEdit($jurisdiction))->toBeTrue();
    expect(\App\Filament\Resources\SigningAuthorityResource::canEdit($signingAuthority))->toBeTrue();
    expect(\App\Filament\Resources\SigningAuthorityResource::canDelete($signingAuthority))->toBeTrue();
});

// ── 2. All roles can VIEW the Organization Visualization page ────────────────

it('system_admin and legal can access org visualization page', function () {
    // OrgVisualizationPage canAccess allows system_admin and legal
    $this->get('/admin/org-visualization-page')->assertSuccessful();

    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);
    $this->get('/admin/org-visualization-page')->assertSuccessful();
});

// ══════════════════════════════════════════════════════════════════════════════
// REGIONS
// ══════════════════════════════════════════════════════════════════════════════

// ── 3. Creating a Region with name and code succeeds ─────────────────────────

it('can create a region with name and code via Livewire form', function () {
    Livewire::test(CreateRegion::class)
        ->fillForm([
            'name' => 'Middle East',
            'code' => 'AE',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('regions', [
        'name' => 'Middle East',
        'code' => 'AE',
    ]);
});

it('can render region list page', function () {
    $this->get('/admin/regions')->assertSuccessful();
});

// ── 4. Region code must be unique — duplicate codes fail validation ──────────

it('region code must be unique at database level', function () {
    Region::create(['name' => 'First Region', 'code' => 'AE']);

    // Attempting to create a duplicate region code should fail
    // The unique constraint is at the database level
    expect(fn () => Region::create(['name' => 'Second Region', 'code' => 'AE']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('can edit a region via Livewire form', function () {
    $region = Region::create(['name' => 'Before Edit', 'code' => 'GB']);

    Livewire::test(EditRegion::class, ['record' => $region->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($region->fresh()->name)->toBe('After Edit');
});

// ══════════════════════════════════════════════════════════════════════════════
// ENTITIES
// ══════════════════════════════════════════════════════════════════════════════

// ── 5. Creating an Entity requires selecting a Region ────────────────────────

it('creating an entity requires a region', function () {
    Livewire::test(CreateEntity::class)
        ->fillForm([
            'region_id' => null,
            'name' => 'No Region Entity',
            'code' => 'NRE',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'region_id' => 'required',
        ]);
});

it('can create an entity with a valid region', function () {
    $region = Region::create(['name' => 'Entity Test Region', 'code' => 'US']);

    Livewire::test(CreateEntity::class)
        ->fillForm([
            'region_id' => $region->id,
            'name' => 'New Test Entity',
            'code' => 'NTE',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('entities', [
        'name' => 'New Test Entity',
        'code' => 'NTE',
        'region_id' => $region->id,
    ]);
});

// ── 6. Entity code must be unique (scoped to region) ─────────────────────────

it('entity code must be unique within the same region', function () {
    $region = Region::create(['name' => 'Unique Code Region', 'code' => 'GB']);
    Entity::create(['region_id' => $region->id, 'name' => 'First Entity', 'code' => 'DUP']);

    // Unique constraint is (region_id, code) at database level
    expect(fn () => Entity::create(['region_id' => $region->id, 'name' => 'Second Entity', 'code' => 'DUP']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('same entity code is allowed in different regions', function () {
    $region1 = Region::create(['name' => 'Region One', 'code' => 'US']);
    $region2 = Region::create(['name' => 'Region Two', 'code' => 'GB']);

    Entity::create(['region_id' => $region1->id, 'name' => 'Entity A', 'code' => 'SAME']);
    Entity::create(['region_id' => $region2->id, 'name' => 'Entity B', 'code' => 'SAME']);

    expect(Entity::where('code', 'SAME')->count())->toBe(2);
});

// ── 7. Parent Entity field creates hierarchical relationship ─────────────────

it('parent entity field creates hierarchical relationship', function () {
    $region = Region::create(['name' => 'Hierarchy Region', 'code' => 'AE']);
    $parent = Entity::create(['region_id' => $region->id, 'name' => 'Parent Corp', 'code' => 'PC']);

    Livewire::test(CreateEntity::class)
        ->fillForm([
            'region_id' => $region->id,
            'name' => 'Child Entity',
            'code' => 'CE',
            'parent_entity_id' => $parent->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $child = Entity::where('name', 'Child Entity')->first();
    expect($child)->not->toBeNull();
    expect($child->parent_entity_id)->toBe($parent->id);
    expect($child->parent->id)->toBe($parent->id);
    expect($parent->children->first()->id)->toBe($child->id);
});

// ── 8. Jurisdictions can be attached to an Entity via relation manager ────────

it('entity has jurisdictions relationship via BelongsToMany', function () {
    $region = Region::create(['name' => 'Jurisdiction Region', 'code' => 'US']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Jurisdiction Entity', 'code' => 'JE']);
    $jurisdiction = Jurisdiction::create(['name' => 'DIFC', 'country_code' => 'AE', 'is_active' => true]);

    // Attach jurisdiction via the pivot table
    $entity->jurisdictions()->attach($jurisdiction->id, [
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'license_number' => 'LIC-001',
        'license_expiry' => '2027-12-31',
        'is_primary' => true,
    ]);

    expect($entity->jurisdictions()->count())->toBe(1);
    $attached = $entity->jurisdictions()->first();
    expect($attached->id)->toBe($jurisdiction->id);
    expect($attached->pivot->license_number)->toBe('LIC-001');
    expect((bool) $attached->pivot->is_primary)->toBeTrue();
});

it('can render entity edit page with jurisdictions relation manager', function () {
    $region = Region::create(['name' => 'RM Region', 'code' => 'GB']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'RM Entity', 'code' => 'RM']);

    $this->get("/admin/entities/{$entity->id}/edit")->assertSuccessful();
});

// ══════════════════════════════════════════════════════════════════════════════
// PROJECTS
// ══════════════════════════════════════════════════════════════════════════════

// ── 9. Creating a Project requires selecting an Entity ───────────────────────

it('creating a project requires an entity', function () {
    Livewire::test(CreateProject::class)
        ->fillForm([
            'entity_id' => null,
            'name' => 'No Entity Project',
            'code' => 'NEP',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'entity_id' => 'required',
        ]);
});

it('can create a project with a valid entity', function () {
    $region = Region::create(['name' => 'Project Region', 'code' => 'US']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Project Entity', 'code' => 'PE']);

    Livewire::test(CreateProject::class)
        ->fillForm([
            'entity_id' => $entity->id,
            'name' => 'TiTo Platform v2',
            'code' => 'TPV',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('projects', [
        'name' => 'TiTo Platform v2',
        'code' => 'TPV',
        'entity_id' => $entity->id,
    ]);
});

// ── 10. Project code must be unique (scoped to entity) ───────────────────────

it('project code must be unique within the same entity', function () {
    $region = Region::create(['name' => 'ProjUniq Region', 'code' => 'AE']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'ProjUniq Entity', 'code' => 'PU']);

    Project::create(['entity_id' => $entity->id, 'name' => 'First Project', 'code' => 'DUP']);

    expect(fn () => Project::create(['entity_id' => $entity->id, 'name' => 'Second Project', 'code' => 'DUP']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('same project code is allowed in different entities', function () {
    $region = Region::create(['name' => 'Multi Entity Region', 'code' => 'GB']);
    $entity1 = Entity::create(['region_id' => $region->id, 'name' => 'Entity One', 'code' => 'E1']);
    $entity2 = Entity::create(['region_id' => $region->id, 'name' => 'Entity Two', 'code' => 'E2']);

    Project::create(['entity_id' => $entity1->id, 'name' => 'Project A', 'code' => 'SAME']);
    Project::create(['entity_id' => $entity2->id, 'name' => 'Project B', 'code' => 'SAME']);

    expect(Project::where('code', 'SAME')->count())->toBe(2);
});

// ══════════════════════════════════════════════════════════════════════════════
// DEPENDENCIES
// ══════════════════════════════════════════════════════════════════════════════

// ── 11. Cannot create Entity if no Regions exist (form has no region options) ─

it('entity create form has no region options when no regions exist', function () {
    // No regions created in this test
    expect(Region::count())->toBe(0);

    // The form will render but the region_id select has no options
    $component = Livewire::test(CreateEntity::class);

    $component->fillForm([
            'region_id' => null,
            'name' => 'Orphan Entity',
        ])
        ->call('create')
        ->assertHasFormErrors(['region_id' => 'required']);
});

// ── 12. Cannot create Project if no Entities exist ───────────────────────────

it('project create form has no entity options when no entities exist', function () {
    expect(Entity::count())->toBe(0);

    $component = Livewire::test(CreateProject::class);

    $component->fillForm([
            'entity_id' => null,
            'name' => 'Orphan Project',
        ])
        ->call('create')
        ->assertHasFormErrors(['entity_id' => 'required']);
});

// ══════════════════════════════════════════════════════════════════════════════
// JURISDICTIONS
// ══════════════════════════════════════════════════════════════════════════════

// ── 13. Jurisdiction has: name, country_code, regulatory_body, description ───

it('can create a jurisdiction with all fields via Livewire form', function () {
    Livewire::test(CreateJurisdiction::class)
        ->fillForm([
            'name' => 'UAE - DIFC',
            'country_code' => 'AE',
            'regulatory_body' => 'DFSA',
            'notes' => 'Dubai International Financial Centre regulatory body.',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('jurisdictions', [
        'name' => 'UAE - DIFC',
        'country_code' => 'AE',
        'regulatory_body' => 'DFSA',
    ]);

    $jurisdiction = Jurisdiction::where('name', 'UAE - DIFC')->first();
    expect($jurisdiction->notes)->toBe('Dubai International Financial Centre regulatory body.');
    expect($jurisdiction->is_active)->toBeTrue();
});

it('jurisdiction requires name and country_code', function () {
    Livewire::test(CreateJurisdiction::class)
        ->fillForm([
            'name' => null,
            'country_code' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'country_code' => 'required',
        ]);
});

it('can render jurisdiction list page', function () {
    $this->get('/admin/jurisdictions')->assertSuccessful();
});

// ══════════════════════════════════════════════════════════════════════════════
// SIGNING AUTHORITIES
// ══════════════════════════════════════════════════════════════════════════════

// ── 14. Signing authority links user + entity + optional project ──────────────

it('can create a signing authority with entity and email via Livewire form', function () {
    $region = Region::create(['name' => 'SA Region', 'code' => 'AE']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'SA Entity', 'code' => 'SA']);
    $saUser = User::factory()->create();

    Livewire::test(CreateSigningAuthority::class)
        ->fillForm([
            'entity_id' => $entity->id,
            'user_id' => $saUser->id,
            'user_email' => 'signer@digittal.io',
            'role_or_name' => 'General Counsel',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('signing_authority', [
        'entity_id' => $entity->id,
        'user_email' => 'signer@digittal.io',
        'role_or_name' => 'General Counsel',
    ]);
});

it('signing authority can optionally reference a project', function () {
    $region = Region::create(['name' => 'SA Proj Region', 'code' => 'US']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'SA Proj Entity', 'code' => 'SP']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'SA Project', 'code' => 'SAP']);
    $saUser = User::factory()->create();

    Livewire::test(CreateSigningAuthority::class)
        ->fillForm([
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'user_id' => $saUser->id,
            'user_email' => 'project-signer@digittal.io',
            'role_or_name' => 'CFO',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $sa = SigningAuthority::where('user_email', 'project-signer@digittal.io')->first();
    expect($sa)->not->toBeNull();
    expect($sa->entity_id)->toBe($entity->id);
    expect($sa->project_id)->toBe($project->id);
    expect($sa->role_or_name)->toBe('CFO');
});

// ── 15-18. Authority validation tests ────────────────────────────────────────

it('signing authority requires entity_id', function () {
    Livewire::test(CreateSigningAuthority::class)
        ->fillForm([
            'entity_id' => null,
            'user_email' => 'test@example.com',
            'role_or_name' => 'CEO',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'entity_id' => 'required',
        ]);
});

it('signing authority requires user_email', function () {
    $region = Region::create(['name' => 'Email Region', 'code' => 'GB']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Email Entity', 'code' => 'EM']);

    Livewire::test(CreateSigningAuthority::class)
        ->fillForm([
            'entity_id' => $entity->id,
            'user_email' => null,
            'role_or_name' => 'CEO',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'user_email' => 'required',
        ]);
});

it('signing authority requires role_or_name', function () {
    $region = Region::create(['name' => 'Role Region', 'code' => 'FR']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Role Entity', 'code' => 'RE']);

    Livewire::test(CreateSigningAuthority::class)
        ->fillForm([
            'entity_id' => $entity->id,
            'user_email' => 'test@example.com',
            'role_or_name' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'role_or_name' => 'required',
        ]);
});

it('signing authority user_email must be valid email format', function () {
    $region = Region::create(['name' => 'Format Region', 'code' => 'DE']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Format Entity', 'code' => 'FE']);

    Livewire::test(CreateSigningAuthority::class)
        ->fillForm([
            'entity_id' => $entity->id,
            'user_email' => 'not-an-email',
            'role_or_name' => 'CEO',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'user_email' => 'email',
        ]);
});

it('signing authority belongs to entity and project relationships', function () {
    $region = Region::create(['name' => 'Rel Region', 'code' => 'JP']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Rel Entity', 'code' => 'RL']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'Rel Project', 'code' => 'RP']);

    $saUser = User::factory()->create();
    $sa = SigningAuthority::create([
        'entity_id' => $entity->id,
        'project_id' => $project->id,
        'user_id' => $saUser->id,
        'user_email' => 'rel-test@example.com',
        'role_or_name' => 'VP Legal',
    ]);

    expect($sa->entity->id)->toBe($entity->id);
    expect($sa->project->id)->toBe($project->id);
    expect($entity->signingAuthorities->first()->id)->toBe($sa->id);
});

it('can render signing authority list page', function () {
    $this->get('/admin/signing-authorities')->assertSuccessful();
});

// ══════════════════════════════════════════════════════════════════════════════
// FACTORY SMOKE TESTS
// ══════════════════════════════════════════════════════════════════════════════

it('region factory creates valid record', function () {
    $region = Region::factory()->create();
    expect($region->id)->not->toBeNull();
    expect($region->name)->not->toBeNull();
    expect($region->code)->not->toBeNull();
});

it('entity factory creates valid record with region', function () {
    $entity = Entity::factory()->create();
    expect($entity->id)->not->toBeNull();
    expect($entity->region_id)->not->toBeNull();
    expect($entity->region)->not->toBeNull();
});

it('project factory creates valid record with entity', function () {
    $project = Project::factory()->create();
    expect($project->id)->not->toBeNull();
    expect($project->entity_id)->not->toBeNull();
    expect($project->entity)->not->toBeNull();
});

it('jurisdiction factory creates valid record', function () {
    $jurisdiction = Jurisdiction::factory()->create();
    expect($jurisdiction->id)->not->toBeNull();
    expect($jurisdiction->name)->not->toBeNull();
    expect($jurisdiction->country_code)->not->toBeNull();
    expect($jurisdiction->is_active)->toBeTrue();
});

it('signing authority factory creates valid record', function () {
    $sa = SigningAuthority::factory()->create();
    expect($sa->id)->not->toBeNull();
    expect($sa->entity_id)->not->toBeNull();
    expect($sa->user_email)->not->toBeNull();
    expect($sa->role_or_name)->not->toBeNull();
});

// ══════════════════════════════════════════════════════════════════════════════
// ORG HIERARCHY CHAIN
// ══════════════════════════════════════════════════════════════════════════════

it('full org hierarchy chain: region -> entity -> project works correctly', function () {
    $region = Region::create(['name' => 'MENA', 'code' => 'AE']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Digittal UAE', 'code' => 'DU']);
    $project = Project::create(['entity_id' => $entity->id, 'name' => 'TiTo Platform', 'code' => 'TP']);

    // Navigate relationships
    expect($region->entities->first()->id)->toBe($entity->id);
    expect($entity->region->id)->toBe($region->id);
    expect($entity->projects->first()->id)->toBe($project->id);
    expect($project->entity->id)->toBe($entity->id);

    // Verify chain
    expect($project->entity->region->id)->toBe($region->id);
});
