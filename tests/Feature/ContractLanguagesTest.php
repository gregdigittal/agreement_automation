<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\ContractLanguage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContractLanguagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_attach_language_versions_to_contract(): void
    {
        Storage::fake(config('ccrs.contracts_disk'));

        $contract = Contract::factory()->create();

        ContractLanguage::create([
            'id'           => Str::uuid()->toString(),
            'contract_id'  => $contract->id,
            'language_code'=> 'en',
            'is_primary'   => true,
            'storage_path' => 'contract_languages/test_en.pdf',
            'file_name'    => 'test_en.pdf',
        ]);

        ContractLanguage::create([
            'id'           => Str::uuid()->toString(),
            'contract_id'  => $contract->id,
            'language_code'=> 'fr',
            'is_primary'   => false,
            'storage_path' => 'contract_languages/test_fr.pdf',
            'file_name'    => 'test_fr.pdf',
        ]);

        $contract->refresh();
        $this->assertCount(2, $contract->languages);
        $this->assertEquals('en', $contract->primaryLanguage->language_code);
    }

    public function test_unique_language_per_contract_enforced(): void
    {
        $contract = Contract::factory()->create();
        ContractLanguage::create([
            'id' => Str::uuid()->toString(),
            'contract_id' => $contract->id,
            'language_code' => 'en',
            'is_primary' => true,
            'storage_path' => 'en.pdf',
            'file_name' => 'en.pdf',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ContractLanguage::create([
            'id' => Str::uuid()->toString(),
            'contract_id' => $contract->id,
            'language_code' => 'en',
            'is_primary' => false,
            'storage_path' => 'en2.pdf',
            'file_name' => 'en2.pdf',
        ]);
    }
}
