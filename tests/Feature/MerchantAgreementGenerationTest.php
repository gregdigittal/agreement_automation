<?php

namespace Tests\Feature;

use App\Models\Counterparty;
use App\Models\Entity;
use App\Models\MerchantAgreement;
use App\Models\Project;
use App\Models\Region;
use App\Models\SigningAuthority;
use App\Models\User;
use App\Services\MerchantAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchantAgreementGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_docx_and_creates_contract(): void
    {
        Storage::fake(config('ccrs.contracts_disk'));

        $templatePath = tempnam(sys_get_temp_dir(), 'test_ma_') . '.docx';
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Vendor: ${vendor_name}, Fee: ${merchant_fee}');
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($templatePath);

        Storage::disk(config('ccrs.contracts_disk'))->put('templates/merchant_agreement_master.docx', file_get_contents($templatePath));
        @unlink($templatePath);

        $region = Region::factory()->create();
        $entity = Entity::factory()->create(['region_id' => $region->id]);
        $project = Project::factory()->create(['entity_id' => $entity->id]);
        $counterparty = Counterparty::factory()->create();
        SigningAuthority::factory()->create(['entity_id' => $entity->id, 'project_id' => $project->id]);
        $user = User::create([
            'id' => Str::uuid()->toString(),
            'email' => 'test@test.com',
            'name' => 'Test User',
        ]);

        $agreement = MerchantAgreement::create([
            'id' => Str::uuid()->toString(),
            'counterparty_id' => $counterparty->id,
            'region_id' => $region->id,
            'entity_id' => $entity->id,
            'project_id' => $project->id,
            'merchant_fee' => 1.5,
            'region_terms' => 'Test terms',
        ]);

        $contract = app(MerchantAgreementService::class)->generateFromAgreement($agreement, $user);

        $this->assertNotNull($contract->id);
        $this->assertEquals('Merchant', $contract->contract_type);
        $this->assertNotNull($contract->storage_path);
        Storage::disk(config('ccrs.contracts_disk'))->assertExists($contract->storage_path);
    }
}
