<?php
namespace App\Filament\Widgets;

use App\Models\Contract;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractStatusWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Contract Overview';
    protected ?string $description = 'Current contract counts by workflow stage';
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return [
            Stat::make('Total Contracts', Contract::count()),
            Stat::make('Draft', Contract::where('workflow_state', 'draft')->count()),
            Stat::make('In Review', Contract::whereIn('workflow_state', ['review', 'approval'])->count())->color('warning'),
            Stat::make('Executed', Contract::where('workflow_state', 'executed')->count())->color('success'),
        ];
    }
}
