<?php
namespace App\Filament\Widgets;

use App\Models\WorkflowInstance;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PendingWorkflowsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $userRole = auth()->user()?->roles?->first()?->name;
        $count = WorkflowInstance::where('state', 'active')
            ->with('template')
            ->get()
            ->filter(function ($instance) use ($userRole) {
                $stages = $instance->template->stages ?? [];
                foreach ($stages as $stage) {
                    if (($stage['name'] ?? '') === $instance->current_stage) {
                        $approverRole = $stage['approver_role'] ?? null;
                        return $approverRole !== null && $approverRole === $userRole;
                    }
                }
                return false;
            })
            ->count();

        return [Stat::make('Pending Your Approval', $count)->color('warning')];
    }
}
