<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RiskDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Risk Distribution';
    protected static ?string $description = 'Contracts by AI risk score grouped by region';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $results = DB::table('contracts')
            ->join('regions', 'contracts.region_id', '=', 'regions.id')
            ->leftJoin('ai_analysis_results', function ($join) {
                $join->on('ai_analysis_results.contract_id', '=', 'contracts.id')
                    ->where('ai_analysis_results.analysis_type', '=', 'risk');
            })
            ->select(
                'regions.name as region_name',
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ai_analysis_results.result, '$.risk_level')), 'unscored') as risk_level"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotIn('contracts.workflow_state', ['cancelled'])
            ->groupBy('regions.name', 'risk_level')
            ->orderBy('regions.name')
            ->get();

        $regions = $results->pluck('region_name')->unique()->values();
        $riskLevels = ['high', 'medium', 'low', 'unscored'];
        $colors = [
            'high' => '#ef4444',
            'medium' => '#f59e0b',
            'low' => '#22c55e',
            'unscored' => '#9ca3af',
        ];

        $datasets = [];
        foreach ($riskLevels as $level) {
            $data = [];
            foreach ($regions as $region) {
                $match = $results->where('region_name', $region)->where('risk_level', $level)->first();
                $data[] = $match ? $match->count : 0;
            }
            $datasets[] = [
                'label' => ucfirst($level),
                'data' => $data,
                'backgroundColor' => $colors[$level],
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $regions->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
            'scales' => [
                'x' => ['stacked' => true, 'beginAtZero' => true],
                'y' => ['stacked' => true],
            ],
        ];
    }
}
