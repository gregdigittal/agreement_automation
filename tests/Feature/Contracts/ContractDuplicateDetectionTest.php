<?php

namespace Tests\Feature\Contracts;

use App\Models\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractDuplicateDetectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_detected_by_file_hash(): void
    {
        $contract = Contract::factory()->create([
            'file_hash' => hash('sha256', 'test-content'),
            'file_name' => 'contract-a.pdf',
        ]);

        $duplicates = Contract::where('file_hash', hash('sha256', 'test-content'))
            ->where('id', '!=', $contract->id)
            ->get();

        // No other contract with same hash yet
        $this->assertCount(0, $duplicates);

        // Create a second contract with same hash
        $contract2 = Contract::factory()->create([
            'file_hash' => hash('sha256', 'test-content'),
            'file_name' => 'contract-b.pdf',
        ]);

        $duplicates = Contract::where(function ($q) use ($contract2) {
            $q->where('file_hash', $contract2->file_hash)
              ->orWhere('file_name', $contract2->file_name);
        })
        ->where('id', '!=', $contract2->id)
        ->get();

        $this->assertCount(1, $duplicates);
        $this->assertEquals($contract->id, $duplicates->first()->id);
    }

    public function test_duplicate_detected_by_file_name(): void
    {
        $contract = Contract::factory()->create([
            'file_hash' => hash('sha256', 'content-a'),
            'file_name' => 'master-services-agreement.pdf',
        ]);

        $contract2 = Contract::factory()->create([
            'file_hash' => hash('sha256', 'content-b'),
            'file_name' => 'master-services-agreement.pdf',
        ]);

        $duplicates = Contract::where(function ($q) use ($contract2) {
            $q->where('file_hash', $contract2->file_hash)
              ->orWhere('file_name', $contract2->file_name);
        })
        ->where('id', '!=', $contract2->id)
        ->get();

        $this->assertCount(1, $duplicates);
    }

    public function test_no_duplicate_when_different_hash_and_name(): void
    {
        Contract::factory()->create([
            'file_hash' => hash('sha256', 'content-a'),
            'file_name' => 'contract-a.pdf',
        ]);

        $contract2 = Contract::factory()->create([
            'file_hash' => hash('sha256', 'content-b'),
            'file_name' => 'contract-b.pdf',
        ]);

        $duplicates = Contract::where(function ($q) use ($contract2) {
            $q->where('file_hash', $contract2->file_hash)
              ->orWhere('file_name', $contract2->file_name);
        })
        ->where('id', '!=', $contract2->id)
        ->get();

        $this->assertCount(0, $duplicates);
    }
}
