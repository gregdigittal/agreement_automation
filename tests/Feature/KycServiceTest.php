<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\EntityJurisdiction;
use App\Models\Jurisdiction;
use App\Models\KycTemplate;
use App\Models\KycTemplateItem;
use App\Models\Project;
use App\Models\Region;
use App\Models\User;
use App\Services\KycService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = new KycService();
    $this->region = Region::create(['name' => 'Middle East']);
    $this->jurisdiction = Jurisdiction::factory()->create(['name' => 'UAE - DIFC']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Digittal DIFC']);
    EntityJurisdiction::create([
        'entity_id' => $this->entity->id,
        'jurisdiction_id' => $this->jurisdiction->id,
        'is_primary' => true,
    ]);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'P1']);
    $this->counterparty = Counterparty::create(['legal_name' => 'CP1', 'status' => 'Active']);
    $this->contract = Contract::create([
        'region_id' => $this->region->id, 'entity_id' => $this->entity->id,
        'project_id' => $this->project->id, 'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial', 'title' => 'KYC Test Contract',
    ]);
});

it('matches most specific template', function () {
    $specific = KycTemplate::create(['name' => 'DIFC Commercial', 'entity_id' => $this->entity->id, 'jurisdiction_id' => $this->jurisdiction->id, 'contract_type_pattern' => 'Commercial', 'status' => 'active']);
    KycTemplate::create(['name' => 'Global', 'status' => 'active']);
    expect($this->service->findMatchingTemplate($this->contract)->id)->toBe($specific->id);
});

it('falls back to global wildcard', function () {
    $global = KycTemplate::create(['name' => 'Global', 'status' => 'active']);
    expect($this->service->findMatchingTemplate($this->contract)->id)->toBe($global->id);
});

it('returns null when no template matches', function () {
    expect($this->service->findMatchingTemplate($this->contract))->toBeNull();
});

it('creates immutable KYC pack from template', function () {
    $t = KycTemplate::create(['name' => 'Standard', 'status' => 'active', 'version' => 1]);
    KycTemplateItem::create(['kyc_template_id' => $t->id, 'sort_order' => 0, 'label' => 'Cert of Inc', 'field_type' => 'file_upload', 'is_required' => true]);
    KycTemplateItem::create(['kyc_template_id' => $t->id, 'sort_order' => 1, 'label' => 'Director Name', 'field_type' => 'text', 'is_required' => true]);
    $pack = $this->service->createPackForContract($this->contract);
    expect($pack->status)->toBe('incomplete');
    expect($pack->items)->toHaveCount(2);
});

it('does not create duplicate packs', function () {
    KycTemplate::create(['name' => 'Standard', 'status' => 'active', 'version' => 1]);
    $p1 = $this->service->createPackForContract($this->contract);
    $this->contract->refresh();
    $p2 = $this->service->createPackForContract($this->contract);
    expect($p1->id)->toBe($p2->id);
});

it('marks pack complete when all required items done', function () {
    $t = KycTemplate::create(['name' => 'Standard', 'status' => 'active', 'version' => 1]);
    KycTemplateItem::create(['kyc_template_id' => $t->id, 'sort_order' => 0, 'label' => 'Company Name', 'field_type' => 'text', 'is_required' => true]);
    $pack = $this->service->createPackForContract($this->contract);
    $this->service->completeItem($pack->items->first(), value: 'Digittal FZ-LLC');
    expect($pack->fresh()->status)->toBe('complete');
});

it('reports contract not ready when KYC incomplete', function () {
    $t = KycTemplate::create(['name' => 'Standard', 'status' => 'active', 'version' => 1]);
    KycTemplateItem::create(['kyc_template_id' => $t->id, 'sort_order' => 0, 'label' => 'ID Doc', 'field_type' => 'file_upload', 'is_required' => true]);
    $this->service->createPackForContract($this->contract);
    $this->contract->refresh();
    expect($this->service->isReadyForSigning($this->contract))->toBeFalse();
});
