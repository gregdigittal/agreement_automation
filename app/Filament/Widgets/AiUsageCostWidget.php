<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AiUsageCostWidget extends ChartWidget
{
    protected static ?string $heading = 'AI Usage & Cost';

    protected static ?string $description = 'Daily token usage and estimated cost over last 30 days';

    protected static ?int $sort = 5;

    protected function getData(): array
    {
        // date() is case-insensitive and works on both MySQL and SQLite.
        $results = DB::table('ai_analysis_results')
            ->select(
                DB::raw('date(created_at) as day'),
                DB::raw('SUM(COALESCE(token_usage_input, 0)) as input_tokens'),
                DB::raw('SUM(COALESCE(token_usage_output, 0)) as output_tokens'),
                DB::raw('COUNT(*) as analysis_count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $inputRate = (float) config('ccrs.ai.cost_per_input_mtok', 3.0);
        $outputRate = (float) config('ccrs.ai.cost_per_output_mtok', 15.0);

        $labels = [];
        $tokenData = [];
        $costData = [];

        foreach ($results as $row) {
            $labels[] = \Carbon\Carbon::parse($row->day)->format('M d');
            $inputTokens = (int) ($row->input_tokens ?? 0);
            $outputTokens = (int) ($row->output_tokens ?? 0);
            $tokenData[] = $inputTokens + $outputTokens;
            $costData[] = round(
                ($inputTokens / 1_000_000 * $inputRate) + ($outputTokens / 1_000_000 * $outputRate),
                2
            );
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Tokens',
                    'data' => $tokenData,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'yAxisID' => 'y',
                    'fill' => true,
                ],
                [
                    'label' => 'Estimated Cost ($)',
                    'data' => $costData,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'yAxisID' => 'y1',
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'title' => ['display' => true, 'text' => 'Tokens'],
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'title' => ['display' => true, 'text' => 'Cost ($)'],
                    'grid' => ['drawOnChartArea' => false],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => true, 'position' => 'bottom'],
            ],
        ];
    }
}
