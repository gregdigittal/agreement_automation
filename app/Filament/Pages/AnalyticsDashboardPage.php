<?php

namespace App\Filament\Pages;

use App\Filament\Widgets;
use Filament\Pages\Page;

class AnalyticsDashboardPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-bar';
    protected static ?string $navigationLabel = 'Analytics Dashboard';
    protected static ?string $title = 'Executive Analytics Dashboard';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 32;
    protected static string $view = 'filament.pages.analytics-dashboard';

    public static function shouldRegisterNavigation(): bool
    {
        return config('features.advanced_analytics', false);
    }

    public static function canAccess(): bool
    {
        if (! config('features.advanced_analytics', false)) {
            return false;
        }

        $user = auth()->user();

        return $user && $user->hasAnyRole(['system_admin', 'legal', 'finance', 'audit']);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\ContractPipelineFunnelWidget::class,
            Widgets\RiskDistributionWidget::class,
            Widgets\ComplianceOverviewWidget::class,
            Widgets\ObligationTrackerWidget::class,
            Widgets\AiUsageCostWidget::class,
            Widgets\WorkflowPerformanceWidget::class,
        ];
    }

    protected function getHeaderWidgetsColumns(): int|array
    {
        return 2;
    }
}
