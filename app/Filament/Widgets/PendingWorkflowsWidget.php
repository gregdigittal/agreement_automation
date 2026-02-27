<?php
namespace App\Filament\Widgets;

use App\Models\WorkflowInstance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingWorkflowsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Pending Approvals';
    protected ?string $description = 'Items awaiting your action';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        $userRole = auth()->user()?->roles?->first()?->name;

        if (!$userRole) {
            return [Stat::make('Pending Your Approval', 0)->color('warning')];
        }

        $count = WorkflowInstance::where('state', 'active')
            ->with('template:id,stages')
            ->get(['id', 'current_stage', 'template_id'])
            ->filter(function ($instance) use ($userRole) {
                $stages = $instance->template->stages ?? [];
                foreach ($stages as $stage) {
                    if (($stage['name'] ?? '') === $instance->current_stage) {
                        return ($stage['approver_role'] ?? null) === $userRole;
                    }
                }
                return false;
            })
            ->count();

        return [Stat::make('Pending Your Approval', $count)->color('warning')];
    }
}
