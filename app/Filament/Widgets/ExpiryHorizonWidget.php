<?php
namespace App\Filament\Widgets;

use App\Models\ContractKeyDate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExpiryHorizonWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $now = now();
        return [
            Stat::make('0-30 days', ContractKeyDate::where('date_type', 'expiry_date')->whereBetween('date_value', [$now, $now->copy()->addDays(30)])->count())->color('danger'),
            Stat::make('31-60 days', ContractKeyDate::where('date_type', 'expiry_date')->whereBetween('date_value', [$now->copy()->addDays(31), $now->copy()->addDays(60)])->count())->color('warning'),
            Stat::make('61-90 days', ContractKeyDate::where('date_type', 'expiry_date')->whereBetween('date_value', [$now->copy()->addDays(61), $now->copy()->addDays(90)])->count()),
            Stat::make('90+ days', ContractKeyDate::where('date_type', 'expiry_date')->where('date_value', '>', $now->copy()->addDays(90))->count())->color('success'),
        ];
    }
}
