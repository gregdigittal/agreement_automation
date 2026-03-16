<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class WorkflowPerformanceWidget extends Widget
{
    protected static ?string $heading = 'Workflow Performance';

    protected static ?int $sort = 8;

    protected static string $view = 'filament.widgets.workflow-performance';

    protected int|string|array $columnSpan = 'full';

    public function getPerformanceData(): array
    {
        // workflow_stage_actions columns: id, instance_id, stage_name, action (approve/reject/rework/skip),
        // actor_id, actor_email, comment, artifacts, created_at.
        // No completed_at or sla_deadline — show action-type distribution per stage instead.
        return DB::table('workflow_stage_actions')
            ->select(
                'stage_name',
                DB::raw('COUNT(*) as total_actions'),
                DB::raw("SUM(CASE WHEN action = 'approve' THEN 1 ELSE 0 END) as approvals"),
                DB::raw("SUM(CASE WHEN action = 'reject' THEN 1 ELSE 0 END) as rejections"),
                DB::raw("SUM(CASE WHEN action = 'rework' THEN 1 ELSE 0 END) as reworks"),
                DB::raw("SUM(CASE WHEN action = 'skip' THEN 1 ELSE 0 END) as skips")
            )
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('stage_name')
            ->orderBy('total_actions', 'desc')
            ->get()
            ->map(fn ($row) => [
                'stage_name' => $row->stage_name,
                'total_actions' => (int) $row->total_actions,
                'approvals' => (int) $row->approvals,
                'rejections' => (int) $row->rejections,
                'reworks' => (int) $row->reworks,
                'skips' => (int) $row->skips,
                'rework_rate' => $row->total_actions > 0
                    ? round(($row->reworks / $row->total_actions) * 100, 1)
                    : 0.0,
            ])
            ->toArray();
    }
}
