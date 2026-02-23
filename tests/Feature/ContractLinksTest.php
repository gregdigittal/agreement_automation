<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use App\Services\ContractLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_amendment_linked_to_parent(): void
    {
        $user = User::factory()->create();
        $parent = Contract::factory()->create(['contract_type' => 'Commercial']);

        $child = app(ContractLinkService::class)->createLinkedContract(
            parent: $parent,
            linkType: 'amendment',
            title: 'Amendment No. 1',
            actor: $user,
        );

        $this->assertEquals('Commercial', $child->contract_type);
        $this->assertEquals($parent->counterparty_id, $child->counterparty_id);
        $this->assertEquals($parent->region_id, $child->region_id);
        $this->assertDatabaseHas('contract_links', [
            'parent_contract_id' => $parent->id,
            'child_contract_id'  => $child->id,
            'link_type'          => 'amendment',
        ]);
    }

    public function test_parent_contract_shows_amendments(): void
    {
        $user = User::factory()->create();
        $parent = Contract::factory()->create();

        app(ContractLinkService::class)->createLinkedContract($parent, 'amendment', 'Amend 1', $user);
        app(ContractLinkService::class)->createLinkedContract($parent, 'side_letter', 'Side Letter A', $user);

        $parent->refresh();
        $this->assertCount(1, $parent->amendments);
        $this->assertCount(1, $parent->sideLetters);
    }

    public function test_renewal_extension_updates_parent_expiry(): void
    {
        $user = User::factory()->create();
        $parent = Contract::factory()->create(['expiry_date' => now()->addYear()]);

        $newExpiry = now()->addYears(3)->format('Y-m-d');
        app(ContractLinkService::class)->createLinkedContract(
            parent: $parent,
            linkType: 'renewal',
            title: 'Renewal 2029',
            actor: $user,
            extra: ['renewal_type' => 'extension', 'new_expiry_date' => $newExpiry],
        );

        $parent->refresh();
        $this->assertEquals($newExpiry, $parent->expiry_date->format('Y-m-d'));
    }
}
