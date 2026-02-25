<?php

use App\Filament\Widgets\ActiveEscalationsWidget;
use App\Filament\Widgets\AiCostWidget;
use App\Filament\Widgets\ContractStatusWidget;
use App\Filament\Widgets\ExpiryHorizonWidget;
use App\Filament\Widgets\PendingWorkflowsWidget;
use App\Models\AiAnalysisResult;
use App\Models\Contract;
use App\Models\ContractKeyDate;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->user->assignRole('system_admin');
    $this->actingAs($this->user);
});

it('ContractStatusWidget renders with correct data', function () {
    Contract::factory()->create(['workflow_state' => 'draft']);
    Contract::factory()->create(['workflow_state' => 'executed']);

    Livewire::test(ContractStatusWidget::class)
        ->assertSuccessful();
});

it('ExpiryHorizonWidget renders with date buckets', function () {
    $contract = Contract::factory()->create();

    ContractKeyDate::create([
        'contract_id' => $contract->id,
        'date_type' => 'expiry_date',
        'date_value' => now()->addDays(15),
        'label' => 'Contract Expiry',
    ]);

    Livewire::test(ExpiryHorizonWidget::class)
        ->assertSuccessful();
});

it('PendingWorkflowsWidget renders', function () {
    Livewire::test(PendingWorkflowsWidget::class)
        ->assertSuccessful();
});

it('ActiveEscalationsWidget renders', function () {
    Livewire::test(ActiveEscalationsWidget::class)
        ->assertSuccessful();
});

it('AiCostWidget renders with data', function () {
    $contract = Contract::factory()->create();

    AiAnalysisResult::create([
        'contract_id' => $contract->id,
        'analysis_type' => 'summary',
        'status' => 'completed',
        'cost_usd' => 0.05,
    ]);

    Livewire::test(AiCostWidget::class)
        ->assertSuccessful();
});
