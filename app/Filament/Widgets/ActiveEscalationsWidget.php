<?php
namespace App\Filament\Widgets;

use App\Models\EscalationEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveEscalationsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Escalations', EscalationEvent::whereNull('resolved_at')->count())->color('danger'),
        ];
    }
}
