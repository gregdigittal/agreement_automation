<?php
namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveEscalationsWidget;
use App\Filament\Widgets\ContractStatusWidget;
use App\Filament\Widgets\ExpiryHorizonWidget;
use App\Filament\Widgets\PendingWorkflowsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        return [
            ContractStatusWidget::class,
            ExpiryHorizonWidget::class,
            PendingWorkflowsWidget::class,
            ActiveEscalationsWidget::class,
            \App\Filament\Widgets\AiCostWidget::class,
        ];
    }
}
