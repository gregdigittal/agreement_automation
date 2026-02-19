<?php
namespace App\Filament\Widgets;

use App\Models\AiAnalysisResult;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiCostWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $thirtyDays = now()->subDays(30);
        $results = AiAnalysisResult::where('created_at', '>=', $thirtyDays);
        $totalCost = (clone $results)->sum('cost_usd');
        $totalCount = (clone $results)->count();
        $avgCost = $totalCount > 0 ? $totalCost / $totalCount : 0;

        return [
            Stat::make('Total Cost (30d)', '$' . number_format($totalCost, 2)),
            Stat::make('Total Analyses (30d)', $totalCount),
            Stat::make('Avg Cost/Analysis', '$' . number_format($avgCost, 4)),
        ];
    }
}
