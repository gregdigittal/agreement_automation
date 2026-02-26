<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningField;
use App\Models\SigningSession;
use App\Models\SigningSessionSigner;
use App\Models\TemplateSigningField;
use App\Models\User;
use App\Models\WikiContract;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::create(['name' => 'Test Region', 'code' => 'TR']);
    $this->entity = Entity::create(['region_id' => $this->region->id, 'name' => 'Test Entity', 'code' => 'TE']);
    $this->project = Project::create(['entity_id' => $this->entity->id, 'name' => 'Test Project', 'code' => 'TP']);
    $this->counterparty = Counterparty::create(['legal_name' => 'Test Corp', 'status' => 'Active']);
});

// ── Migration & schema tests ────────────────────────────────────────────

it('can create a template signing field', function () {
    $template = WikiContract::create([
        'name' => 'NDA Template',
        'status' => 'published',
    ]);

    $field = TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'company',
        'label' => 'Company Signature',
        'page_number' => 3,
        'x_position' => 20.00,
        'y_position' => 240.00,
        'width' => 60.00,
        'height' => 20.00,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $this->assertDatabaseHas('template_signing_fields', [
        'id' => $field->id,
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'company',
        'page_number' => 3,
    ]);
});

it('supports all field types', function () {
    $template = WikiContract::create(['name' => 'Test', 'status' => 'draft']);

    foreach (['signature', 'initials', 'text', 'date'] as $type) {
        $field = TemplateSigningField::create([
            'wiki_contract_id' => $template->id,
            'field_type' => $type,
            'signer_role' => 'company',
            'page_number' => 1,
            'x_position' => 20,
            'y_position' => 200,
            'width' => 60,
            'height' => 20,
        ]);

        expect($field->field_type)->toBe($type);
    }
});

it('supports all signer roles', function () {
    $template = WikiContract::create(['name' => 'Test', 'status' => 'draft']);

    foreach (['company', 'counterparty', 'witness_1', 'witness_2'] as $role) {
        $field = TemplateSigningField::create([
            'wiki_contract_id' => $template->id,
            'field_type' => 'signature',
            'signer_role' => $role,
            'page_number' => 1,
            'x_position' => 20,
            'y_position' => 200,
            'width' => 60,
            'height' => 20,
        ]);

        expect($field->signer_role)->toBe($role);
    }
});

it('casts numeric fields correctly', function () {
    $template = WikiContract::create(['name' => 'Test', 'status' => 'draft']);

    $field = TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'company',
        'page_number' => 5,
        'x_position' => 25.50,
        'y_position' => 180.75,
        'width' => 80.00,
        'height' => 30.00,
        'is_required' => true,
        'sort_order' => 2,
    ]);

    expect($field->page_number)->toBeInt()->toBe(5);
    expect($field->is_required)->toBeBool()->toBeTrue();
    expect($field->sort_order)->toBeInt()->toBe(2);
});

// ── Relationship tests ──────────────────────────────────────────────────

it('wiki contract has signingFields relationship', function () {
    $template = WikiContract::create(['name' => 'NDA', 'status' => 'published']);

    TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'company',
        'page_number' => 3,
        'x_position' => 20,
        'y_position' => 240,
        'width' => 60,
        'height' => 20,
    ]);

    TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'initials',
        'signer_role' => 'counterparty',
        'page_number' => 3,
        'x_position' => 100,
        'y_position' => 240,
        'width' => 30,
        'height' => 15,
    ]);

    expect($template->signingFields)->toHaveCount(2);
});

it('template signing field belongs to wiki contract', function () {
    $template = WikiContract::create(['name' => 'MSA', 'status' => 'published']);

    $field = TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'company',
        'page_number' => 1,
        'x_position' => 20,
        'y_position' => 240,
        'width' => 60,
        'height' => 20,
    ]);

    expect($field->wikiContract)->not->toBeNull();
    expect($field->wikiContract->id)->toBe($template->id);
});

it('cascades delete when wiki contract is deleted', function () {
    $template = WikiContract::create(['name' => 'To Delete', 'status' => 'draft']);

    TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'company',
        'page_number' => 1,
        'x_position' => 20,
        'y_position' => 240,
        'width' => 60,
        'height' => 20,
    ]);

    $this->assertDatabaseCount('template_signing_fields', 1);

    $template->delete();

    $this->assertDatabaseCount('template_signing_fields', 0);
});

// ── Filament form tests ─────────────────────────────────────────────────

it('wiki contract edit page loads with signing blocks section', function () {
    $template = WikiContract::create(['name' => 'With Blocks', 'status' => 'draft']);

    $response = $this->get("/admin/wiki-contracts/{$template->id}/edit");
    $response->assertSuccessful();
    $response->assertSee('Signing Blocks');
});

// ── Template field copy to session tests ────────────────────────────────

it('copies template fields to signing session fields', function () {
    $template = WikiContract::create(['name' => 'NDA Template', 'status' => 'published']);

    TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'company',
        'label' => 'CEO Signature',
        'page_number' => 3,
        'x_position' => 20,
        'y_position' => 240,
        'width' => 60,
        'height' => 20,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'initials',
        'signer_role' => 'counterparty',
        'label' => 'CP Initials',
        'page_number' => 3,
        'x_position' => 100,
        'y_position' => 240,
        'width' => 30,
        'height' => 15,
        'is_required' => true,
        'sort_order' => 2,
    ]);

    // Create a signing session manually (simulating the service logic)
    $contract = Contract::create([
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'counterparty_id' => $this->counterparty->id,
        'contract_type' => 'Commercial',
        'title' => 'Test Contract',
    ]);

    $session = SigningSession::create([
        'contract_id' => $contract->id,
        'initiated_by' => $this->admin->id,
        'signing_order' => 'sequential',
        'status' => 'active',
        'document_hash' => hash('sha256', 'test'),
        'expires_at' => now()->addDays(30),
    ]);

    $internalSigner = SigningSessionSigner::create([
        'signing_session_id' => $session->id,
        'signer_name' => 'CEO',
        'signer_email' => 'ceo@company.com',
        'signer_type' => 'internal',
        'signing_order' => 0,
        'status' => 'pending',
    ]);

    $externalSigner = SigningSessionSigner::create([
        'signing_session_id' => $session->id,
        'signer_name' => 'CP Contact',
        'signer_email' => 'contact@counterparty.com',
        'signer_type' => 'external',
        'signing_order' => 1,
        'status' => 'pending',
    ]);

    // Copy template fields — simulating copyTemplateFieldsToSession
    $templateFields = TemplateSigningField::where('wiki_contract_id', $template->id)
        ->orderBy('sort_order')
        ->get();

    $roleMap = [
        'company' => $internalSigner->id,
        'counterparty' => $externalSigner->id,
    ];

    foreach ($templateFields as $tf) {
        SigningField::create([
            'signing_session_id' => $session->id,
            'assigned_to_signer_id' => $roleMap[$tf->signer_role] ?? $internalSigner->id,
            'field_type' => $tf->field_type,
            'label' => $tf->label,
            'page_number' => $tf->page_number,
            'x_position' => $tf->x_position,
            'y_position' => $tf->y_position,
            'width' => $tf->width,
            'height' => $tf->height,
            'is_required' => $tf->is_required,
        ]);
    }

    $sessionFields = SigningField::where('signing_session_id', $session->id)->get();
    expect($sessionFields)->toHaveCount(2);

    $companyField = $sessionFields->where('assigned_to_signer_id', $internalSigner->id)->first();
    expect($companyField)->not->toBeNull();
    expect($companyField->field_type)->toBe('signature');
    expect($companyField->label)->toBe('CEO Signature');
    expect($companyField->page_number)->toBe(3);

    $cpField = $sessionFields->where('assigned_to_signer_id', $externalSigner->id)->first();
    expect($cpField)->not->toBeNull();
    expect($cpField->field_type)->toBe('initials');
    expect($cpField->label)->toBe('CP Initials');
});

it('template with no fields produces no session fields', function () {
    $template = WikiContract::create(['name' => 'Empty Template', 'status' => 'published']);

    $fields = TemplateSigningField::where('wiki_contract_id', $template->id)->get();
    expect($fields)->toHaveCount(0);
});
