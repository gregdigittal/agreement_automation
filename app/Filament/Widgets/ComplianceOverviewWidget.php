<?php

namespace App\Filament\Widgets;

use App\Models\ComplianceFinding;
use Filament\Widgets\ChartWidget;

class ComplianceOverviewWidget extends ChartWidget
{
    protected static ?string $heading = 'Compliance Overview';
    protected static ?string $description = 'Aggregate compliance findings across all active contracts';
    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return config('features.regulatory_compliance', false);
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

        $counts = [];
        foreach ($statuses as $status) {
            $counts[] = ComplianceFinding::where('status', $status)->count();
        }

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
