<?php

namespace App\Filament\Widgets;

use App\Models\ContractKeyDate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpiryHorizonWidget extends StatsOverviewWidget
{
    protected ?string $heading = 'Expiry Horizon';

    protected ?string $description = 'Contracts approaching expiration';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $now = now();

        $expired = ContractKeyDate::where('date_type', 'expiry_date')
            ->where('date_value', '<', $now)
            ->count();

        $within30 = ContractKeyDate::where('date_type', 'expiry_date')
            ->whereBetween('date_value', [$now, $now->copy()->addDays(30)])
            ->count();

        $within60 = ContractKeyDate::where('date_type', 'expiry_date')
            ->whereBetween('date_value', [$now->copy()->addDays(31), $now->copy()->addDays(60)])
            ->count();

        $within90 = ContractKeyDate::where('date_type', 'expiry_date')
            ->whereBetween('date_value', [$now->copy()->addDays(61), $now->copy()->addDays(90)])
            ->count();

        $beyond90 = ContractKeyDate::where('date_type', 'expiry_date')
            ->where('date_value', '>', $now->copy()->addDays(90))
            ->count();

        return [
            Stat::make('Expired', $expired)
                ->description('Past expiry date — action required')
                ->color('danger'),
            Stat::make('0–30 days', $within30)
                ->description('Expiring imminently')
                ->color('danger'),
            Stat::make('31–60 days', $within60)
                ->description('Review and plan renewal')
                ->color('warning'),
            Stat::make('61–90 days', $within90)
                ->description('On the horizon')
                ->color('primary'),
            Stat::make('90+ days', $beyond90)
                ->description('No immediate action needed')
                ->color('success'),
        ];
    }
}
