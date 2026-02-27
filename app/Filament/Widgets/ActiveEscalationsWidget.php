<?php
namespace App\Filament\Widgets;

use App\Models\EscalationEvent;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveEscalationsWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Active Escalations';
    protected ?string $description = 'Unresolved escalation events';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Active Escalations', EscalationEvent::whereNull('resolved_at')->count())->color('danger'),
        ];
    }
}
