<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class ObligationTrackerWidget extends Widget
{
    protected static ?string $heading = 'Obligation Tracker';
    protected static ?int $sort = 4;
    protected static string $view = 'filament.widgets.obligation-tracker';
    protected int|string|array $columnSpan = 'full';

    public function getObligations(): array
    {
        return DB::table('obligations_register')
            ->join('contracts', 'obligations_register.contract_id', '=', 'contracts.id')
            ->select(
                'obligations_register.id',
                'obligations_register.obligation_type',
                'obligations_register.description',
                'obligations_register.due_date',
                'obligations_register.status',
                'contracts.title as contract_title',
                'contracts.id as contract_id'
            )
            ->where('obligations_register.status', '!=', 'completed')
            ->where('obligations_register.due_date', '>=', now()->subDays(30))
            ->where('obligations_register.due_date', '<=', now()->addDays(90))
            ->orderBy('obligations_register.due_date')
            ->limit(50)
            ->get()
            ->map(fn ($ob) => (array) $ob)
            ->toArray();
    }
}
