<?php

namespace App\Filament\Widgets;

use App\Helpers\Feature;
use App\Models\ComplianceFinding;
use Filament\Widgets\ChartWidget;

class ComplianceOverviewWidget extends ChartWidget
{
    protected static ?string $heading = 'Compliance Overview';

    protected static ?string $description = 'Aggregate compliance findings across all active contracts';

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 1;

    public static function canView(): bool
    {
        return Feature::enabled('regulatory_compliance');
    }

    protected function getData(): array
    {
        $statuses = ['compliant', 'non_compliant', 'unclear', 'not_applicable'];
        $colors = [
            'compliant' => '#22c55e',
            'non_compliant' => '#ef4444',
            'unclear' => '#f59e0b',
            'not_applicable' => '#9ca3af',
        ];

        $countsRaw = ComplianceFinding::whereIn('status', $statuses)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $counts = array_map(fn ($s) => (int) ($countsRaw[$s] ?? 0), $statuses);

        return [
            'datasets' => [
                [
                    'data' => $counts,
                    'backgroundColor' => array_values($colors),
                ],
            ],
            'labels' => array_map(fn ($s) => ucwords(str_replace('_', ' ', $s)), $statuses),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
        ];
    }
}
