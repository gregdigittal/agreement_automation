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

    private const STAGES = ['draft', 'review', 'approval', 'signing', 'countersign', 'executed', 'archived'];

    private const COLORS = ['#94a3b8', '#60a5fa', '#fbbf24', '#a78bfa', '#34d399', '#10b981', '#9ca3af'];

    /** @var array<string,int>|null */
    private ?array $cachedCounts = null;

    public function getStageCounts(): array
    {
        if ($this->cachedCounts === null) {
            $raw = Contract::whereIn('workflow_state', self::STAGES)
                ->selectRaw('workflow_state, COUNT(*) as count')
                ->groupBy('workflow_state')
                ->pluck('count', 'workflow_state')
                ->all();

            $this->cachedCounts = array_map(
                fn ($stage) => (int) ($raw[$stage] ?? 0),
                array_combine(self::STAGES, self::STAGES)
            );
        }

        return $this->cachedCounts;
    }

    protected function getData(): array
    {
        $counts = array_values($this->getStageCounts());

        return [
            'datasets' => [
                [
                    'label' => 'Contracts',
                    'data' => $counts,
                    'backgroundColor' => self::COLORS,
                ],
            ],
            'labels' => array_map(fn ($s) => ucwords(str_replace('_', ' ', $s)), self::STAGES),
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
        $parts = [];
        foreach ($this->getStageCounts() as $stage => $count) {
            $parts[] = ucwords(str_replace('_', ' ', $stage)).': '.$count;
        }

        return $this->getHeading().'. '.implode(', ', $parts).'.';
    }
}
