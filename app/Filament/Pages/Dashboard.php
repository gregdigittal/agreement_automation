<?php
namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveEscalationsWidget;
use App\Filament\Widgets\AiCostWidget;
use App\Filament\Widgets\ComplianceOverviewWidget;
use App\Filament\Widgets\ContractStatusWidget;
use App\Filament\Widgets\ExpiryHorizonWidget;
use App\Filament\Widgets\ObligationTrackerWidget;
use App\Filament\Widgets\PendingWorkflowsWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    public function getWidgets(): array
    {
        $widgets = [
            ContractStatusWidget::class,
            ExpiryHorizonWidget::class,
            PendingWorkflowsWidget::class,
            ActiveEscalationsWidget::class,
            AiCostWidget::class,
            ObligationTrackerWidget::class,
        ];

        if (config('features.regulatory_compliance', false)) {
            $widgets[] = ComplianceOverviewWidget::class;
        }

        return $widgets;
    }
}
