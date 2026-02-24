<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class WorkflowPerformanceWidget extends Widget
{
    protected static ?string $heading = 'Workflow Performance';
    protected static ?int $sort = 6;
    protected static string $view = 'filament.widgets.workflow-performance';
    protected int|string|array $columnSpan = 'full';

    public function getPerformanceData(): array
    {
        return DB::table('workflow_stage_actions')
            ->select(
                'stage_name',
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours'),
                DB::raw('MAX(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as max_hours'),
                DB::raw('MIN(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as min_hours'),
                DB::raw('COUNT(*) as total_actions'),
                DB::raw('SUM(CASE WHEN completed_at > sla_deadline THEN 1 ELSE 0 END) as sla_breaches')
            )
            ->whereNotNull('completed_at')
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('stage_name')
            ->orderByRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) DESC')
            ->get()
            ->map(fn ($row) => [
                'stage_name' => $row->stage_name,
                'avg_hours' => round($row->avg_hours ?? 0, 1),
                'max_hours' => round($row->max_hours ?? 0, 1),
                'min_hours' => round($row->min_hours ?? 0, 1),
                'total_actions' => (int) $row->total_actions,
                'sla_breaches' => (int) $row->sla_breaches,
                'sla_breach_rate' => $row->total_actions > 0
                    ? round(($row->sla_breaches / $row->total_actions) * 100, 1)
                    : 0.0,
            ])
            ->toArray();
    }
}
