<?php

namespace App\Filament\Pages;

use App\Models\AiDiscoveryDraft;
use App\Services\AiDiscoveryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class AiDiscoveryReviewPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';
    protected static string $view = 'filament.pages.ai-discovery-review';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?string $title = 'AI Discovery Review';
    protected static ?int $navigationSort = 15;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AiDiscoveryDraft::query()->where('status', 'pending')->with('contract'))
            ->columns([
                Tables\Columns\TextColumn::make('contract.title')->limit(30)->sortable(),
                Tables\Columns\TextColumn::make('draft_type')->badge()
                    ->color(fn ($state) => match ($state) {
                        'counterparty' => 'warning',
                        'entity' => 'info',
                        'jurisdiction' => 'success',
                        'governing_law' => 'primary',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('extracted_data')
                    ->label('Extracted')
                    ->formatStateUsing(fn ($state) => collect($state)->map(fn ($v, $k) => "{$k}: {$v}")->take(3)->join(', '))
                    ->limit(60),
                Tables\Columns\TextColumn::make('confidence')
                    ->badge()
                    ->color(fn ($state) => $state >= 0.8 ? 'success' : ($state >= 0.5 ? 'warning' : 'danger'))
                    ->formatStateUsing(fn ($state) => round($state * 100) . '%'),
                Tables\Columns\TextColumn::make('matched_record_id')
                    ->label('Match')
                    ->formatStateUsing(fn ($state) => $state ? 'Existing record' : 'New record')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (AiDiscoveryDraft $record) {
                        app(AiDiscoveryService::class)->approveDraft($record, auth()->user());
                        Notification::make()->title('Draft approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (AiDiscoveryDraft $record) {
                        app(AiDiscoveryService::class)->rejectDraft($record, auth()->user());
                        Notification::make()->title('Draft rejected')->warning()->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('10s');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = AiDiscoveryDraft::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }
}
