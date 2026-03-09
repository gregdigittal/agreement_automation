<?php

use App\Filament\Pages\AiDiscoveryReviewPage;
use App\Models\AiDiscoveryDraft;
use App\Models\Contract;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('renders page with pending drafts', function () {
    $contract = Contract::factory()->create(['title' => 'Alpha Contract']);
    AiDiscoveryDraft::factory()->counterparty()->create(['contract_id' => $contract->id]);

    Livewire::test(AiDiscoveryReviewPage::class)
        ->assertSuccessful();
});

it('shows drafts from multiple contracts', function () {
    $contract1 = Contract::factory()->create(['title' => 'Contract Alpha']);
    $contract2 = Contract::factory()->create(['title' => 'Contract Beta']);

    AiDiscoveryDraft::factory()->counterparty()->create(['contract_id' => $contract1->id]);
    AiDiscoveryDraft::factory()->jurisdiction()->create(['contract_id' => $contract2->id]);

    Livewire::test(AiDiscoveryReviewPage::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords(AiDiscoveryDraft::where('status', 'pending')->get());
});

it('filters by contract to show only that contracts drafts', function () {
    $contract1 = Contract::factory()->create(['title' => 'Contract Alpha']);
    $contract2 = Contract::factory()->create(['title' => 'Contract Beta']);

    $draft1 = AiDiscoveryDraft::factory()->counterparty()->create(['contract_id' => $contract1->id]);
    $draft2 = AiDiscoveryDraft::factory()->jurisdiction()->create(['contract_id' => $contract2->id]);

    Livewire::test(AiDiscoveryReviewPage::class)
        ->filterTable('contract_id', $contract1->id)
        ->assertCanSeeTableRecords([$draft1])
        ->assertCanNotSeeTableRecords([$draft2]);
});

it('does not show approved drafts', function () {
    $contract = Contract::factory()->create();
    $approved = AiDiscoveryDraft::factory()->counterparty()->approved()->create(['contract_id' => $contract->id]);

    Livewire::test(AiDiscoveryReviewPage::class)
        ->assertCanNotSeeTableRecords([$approved]);
});

it('shows correct navigation badge with pending count', function () {
    $contract = Contract::factory()->create();
    AiDiscoveryDraft::factory()->count(3)->create(['contract_id' => $contract->id, 'status' => 'pending']);
    AiDiscoveryDraft::factory()->approved()->create(['contract_id' => $contract->id]);

    $badge = AiDiscoveryReviewPage::getNavigationBadge();
    expect($badge)->toBe('3');
});
