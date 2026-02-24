<?php

namespace App\Filament\Widgets;

use App\Models\Contract;
use Filament\Widgets\ChartWidget;

class ContractPipelineFunnelWidget extends ChartWidget
{
    protected static ?string $heading = 'Contract Pipeline';
    protected static ?string $description = 'Contract counts by workflow stage';
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $stages = ['draft', 'in_review', 'pending_approval', 'signing', 'executed', 'archived'];

        $counts = [];
        foreach ($stages as $stage) {
            $counts[] = Contract::where('workflow_state', $stage)->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Contracts',
                    'data' => $counts,
                    'backgroundColor' => [
                        '#94a3b8',
                        '#60a5fa',
                        '#fbbf24',
                        '#a78bfa',
                        '#34d399',
                        '#9ca3af',
                    ],
                ],
            ],
            'labels' => array_map(fn ($s) => ucwords(str_replace('_', ' ', $s)), $stages),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }

    protected function getExtraBodyAttributes(): array
    {
        return [
            'role' => 'img',
            'aria-label' => $this->getAccessibleDescription(),
        ];
    }

    protected function getAccessibleDescription(): string
    {
        $data = $this->getData();
        $labels = $data['labels'] ?? [];
        $values = $data['datasets'][0]['data'] ?? [];
        $parts = [];
        foreach ($labels as $i => $label) {
            $parts[] = "{$label}: " . ($values[$i] ?? 0);
        }

        return $this->getHeading() . '. ' . implode(', ', $parts) . '.';
    }
}
