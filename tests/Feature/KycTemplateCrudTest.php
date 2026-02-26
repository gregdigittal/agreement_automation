<?php

use App\Filament\Resources\KycTemplateResource\Pages\CreateKycTemplate;
use App\Filament\Resources\KycTemplateResource\Pages\EditKycTemplate;
use App\Models\Entity;
use App\Models\Jurisdiction;
use App\Models\KycTemplate;
use App\Models\Region;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render KYC template list page', function () {
    $this->get('/admin/kyc-templates')->assertSuccessful();
});

it('can render KYC template create page', function () {
    $this->get('/admin/kyc-templates/create')->assertSuccessful();
});

it('can create a KYC template via Livewire form', function () {
    Livewire::test(CreateKycTemplate::class)
        ->fillForm([
            'name' => 'Standard KYC Checklist',
            'contract_type_pattern' => '*',
            'status' => 'draft',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('kyc_templates', [
        'name' => 'Standard KYC Checklist',
        'status' => 'draft',
    ]);
});

it('validates required fields on KYC template create', function () {
    Livewire::test(CreateKycTemplate::class)
        ->fillForm([
            'name' => null,
            'contract_type_pattern' => null,
            'status' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'contract_type_pattern' => 'required',
            'status' => 'required',
        ]);
});

it('can create KYC template scoped to entity and jurisdiction', function () {
    $region = Region::create(['name' => 'KYC Region', 'code' => 'KR']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'KYC Entity', 'code' => 'KE']);
    $jurisdiction = Jurisdiction::create(['name' => 'KYC Jurisdiction', 'country_code' => 'KJ', 'is_active' => true]);

    Livewire::test(CreateKycTemplate::class)
        ->fillForm([
            'name' => 'Scoped KYC Template',
            'entity_id' => $entity->id,
            'jurisdiction_id' => $jurisdiction->id,
            'contract_type_pattern' => 'Commercial',
            'status' => 'active',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = KycTemplate::where('name', 'Scoped KYC Template')->first();
    expect($template)->not->toBeNull();
    expect($template->entity_id)->toBe($entity->id);
    expect($template->jurisdiction_id)->toBe($jurisdiction->id);
});

it('can edit a KYC template via Livewire form', function () {
    $template = KycTemplate::create([
        'name' => 'Before Edit',
        'status' => 'draft',
    ]);

    Livewire::test(EditKycTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
            'status' => 'active',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $template->refresh();
    expect($template->name)->toBe('After Edit');
    expect($template->status)->toBe('active');
});

it('legal user can create KYC templates', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/kyc-templates/create')->assertSuccessful();
});

it('commercial user cannot create KYC templates', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/kyc-templates/create')->assertForbidden();
});
