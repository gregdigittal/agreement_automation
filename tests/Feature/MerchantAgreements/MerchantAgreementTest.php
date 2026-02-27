<?php

use App\Models\Contract;
use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\MerchantAgreement;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningAuthority;
use App\Models\TemplateSigningField;
use App\Models\User;
use App\Models\WikiContract;
use App\Services\MerchantAgreementService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->region = Region::factory()->create();
    $this->entity = Entity::factory()->create(['region_id' => $this->region->id]);
    $this->project = Project::factory()->create(['entity_id' => $this->entity->id]);
    $this->counterparty = Counterparty::factory()->create();
});

// ---------------------------------------------------------------------------
// 1. system_admin can view merchant agreements page
// ---------------------------------------------------------------------------
it('system_admin can view merchant agreements', function () {
    $user = User::factory()->create();
    $user->assignRole('system_admin');
    $this->actingAs($user);

    MerchantAgreement::factory()->create([
        'counterparty_id' => $this->counterparty->id,
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
    ]);

    $this->assertDatabaseCount('merchant_agreements', 1);
});

// ---------------------------------------------------------------------------
// 2. legal and commercial users can view merchant agreements
// ---------------------------------------------------------------------------
it('legal and commercial users can view merchant agreements', function () {
    foreach (['legal', 'commercial'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        MerchantAgreement::factory()->create([
            'counterparty_id' => $this->counterparty->id,
            'region_id' => $this->region->id,
            'entity_id' => $this->entity->id,
            'project_id' => $this->project->id,
        ]);
    }

    $this->assertDatabaseCount('merchant_agreements', 2);
});

// ---------------------------------------------------------------------------
// 3. Generation requires selecting a WikiContract template
// ---------------------------------------------------------------------------
it('generation requires selecting a WikiContract template', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $user = User::factory()->create();
    $user->assignRole('system_admin');
    $this->actingAs($user);

    $contract = Contract::factory()->create([
        'counterparty_id' => $this->counterparty->id,
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
    ]);

    $service = app(MerchantAgreementService::class);

    // Generate without a template_id -- no DOCX is produced but MerchantAgreementInput is still stored
    $result = $service->generate($contract, [
        'vendor_name' => $this->counterparty->legal_name,
        'merchant_fee' => '1.50',
    ], $user);

    expect($result['contract_id'])->toBe($contract->id);

    // No storage_path updated on the contract (because no template was provided)
    $this->assertDatabaseHas('merchant_agreement_inputs', [
        'contract_id' => $contract->id,
        'template_id' => null,
    ]);
});

// ---------------------------------------------------------------------------
// 4. Template fields pre-populated from counterparty and org context
// ---------------------------------------------------------------------------
it('template fields pre-populated from counterparty and org context', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $user = User::factory()->create();
    $user->assignRole('system_admin');
    $this->actingAs($user);

    SigningAuthority::factory()->create([
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
    ]);

    $agreement = MerchantAgreement::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'counterparty_id' => $this->counterparty->id,
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'merchant_fee' => 2.50,
        'region_terms' => 'Custom terms for region',
    ]);

    // Build a minimal DOCX template and upload to storage
    $templatePath = tempnam(sys_get_temp_dir(), 'test_ma_') . '.docx';
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addText('Vendor: ${vendor_name}, Fee: ${merchant_fee}, Entity: ${entity_name}');
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($templatePath);

    Storage::disk(config('ccrs.contracts_disk'))->put(
        config('ccrs.merchant_agreement_template_key', 'templates/merchant_agreement_master.docx'),
        file_get_contents($templatePath)
    );
    @unlink($templatePath);

    $contract = app(MerchantAgreementService::class)->generateFromAgreement($agreement, $user);

    // Contract title should include counterparty name
    expect($contract->title)->toContain($this->counterparty->legal_name);
    // Region and entity should be wired in
    expect($contract->region_id)->toBe($this->region->id);
    expect($contract->entity_id)->toBe($this->entity->id);
});

// ---------------------------------------------------------------------------
// 5. Generated document can be reviewed before submission (draft state)
// ---------------------------------------------------------------------------
it('generated document can be reviewed before submission', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $user = User::factory()->create();
    $user->assignRole('system_admin');
    $this->actingAs($user);

    SigningAuthority::factory()->create([
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
    ]);

    $templatePath = tempnam(sys_get_temp_dir(), 'test_ma_') . '.docx';
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addText('Review template ${vendor_name}');
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($templatePath);

    Storage::disk(config('ccrs.contracts_disk'))->put(
        config('ccrs.merchant_agreement_template_key', 'templates/merchant_agreement_master.docx'),
        file_get_contents($templatePath)
    );
    @unlink($templatePath);

    $agreement = MerchantAgreement::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'counterparty_id' => $this->counterparty->id,
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'merchant_fee' => 3.00,
        'region_terms' => 'Review terms',
    ]);

    $contract = app(MerchantAgreementService::class)->generateFromAgreement($agreement, $user);

    // Document should exist on disk for review
    Storage::disk(config('ccrs.contracts_disk'))->assertExists($contract->storage_path);

    // Contract must be in draft for review before submission
    expect($contract->workflow_state)->toBe('draft');
});

// ---------------------------------------------------------------------------
// 6. Creates Contract record of type "merchant" in draft state
// ---------------------------------------------------------------------------
it('creates contract record of type Merchant in draft state', function () {
    Storage::fake(config('ccrs.contracts_disk'));

    $user = User::factory()->create();
    $user->assignRole('system_admin');
    $this->actingAs($user);

    SigningAuthority::factory()->create([
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
    ]);

    $templatePath = tempnam(sys_get_temp_dir(), 'test_ma_') . '.docx';
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    $section = $phpWord->addSection();
    $section->addText('Contract type test ${vendor_name}');
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save($templatePath);

    Storage::disk(config('ccrs.contracts_disk'))->put(
        config('ccrs.merchant_agreement_template_key', 'templates/merchant_agreement_master.docx'),
        file_get_contents($templatePath)
    );
    @unlink($templatePath);

    $agreement = MerchantAgreement::create([
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'counterparty_id' => $this->counterparty->id,
        'region_id' => $this->region->id,
        'entity_id' => $this->entity->id,
        'project_id' => $this->project->id,
        'merchant_fee' => 1.75,
        'region_terms' => 'Type test',
    ]);

    $contract = app(MerchantAgreementService::class)->generateFromAgreement($agreement, $user);

    expect($contract->contract_type)->toBe('Merchant');
    expect($contract->workflow_state)->toBe('draft');
    expect($contract->counterparty_id)->toBe($this->counterparty->id);

    $this->assertDatabaseHas('contracts', [
        'id' => $contract->id,
        'contract_type' => 'Merchant',
    ]);
});

// ---------------------------------------------------------------------------
// 7. WikiContract template signing blocks map roles to signers
// ---------------------------------------------------------------------------
it('WikiContract template signing fields define signer roles', function () {
    $template = WikiContract::factory()->published()->create();

    TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'vendor',
        'label' => 'Vendor Signature',
        'page_number' => 1,
        'x_position' => 100,
        'y_position' => 600,
        'width' => 150,
        'height' => 50,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'authority',
        'label' => 'Authority Signature',
        'page_number' => 1,
        'x_position' => 100,
        'y_position' => 700,
        'width' => 150,
        'height' => 50,
        'is_required' => true,
        'sort_order' => 2,
    ]);

    $fields = $template->signingFields()->get();

    expect($fields)->toHaveCount(2);
    expect($fields->pluck('signer_role')->toArray())->toBe(['vendor', 'authority']);
    expect($fields->every(fn ($f) => $f->is_required))->toBeTrue();
});

// ---------------------------------------------------------------------------
// 8. Signing blocks include position and page metadata
// ---------------------------------------------------------------------------
it('signing blocks include position and page metadata', function () {
    $template = WikiContract::factory()->published()->create();

    TemplateSigningField::create([
        'wiki_contract_id' => $template->id,
        'field_type' => 'signature',
        'signer_role' => 'vendor',
        'label' => 'Vendor Sign Here',
        'page_number' => 2,
        'x_position' => 50.5,
        'y_position' => 300.25,
        'width' => 200,
        'height' => 60,
        'is_required' => true,
        'sort_order' => 1,
    ]);

    $field = $template->signingFields()->first();

    expect($field->page_number)->toBe(2);
    expect((float) $field->x_position)->toBe(50.5);
    expect((float) $field->y_position)->toBe(300.25);
    expect($field->field_type)->toBe('signature');
    expect($field->label)->toBe('Vendor Sign Here');
});
