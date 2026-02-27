<?php

namespace App\Filament\Pages;

use App\Models\Notification;
use App\Services\NotificationService;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NotificationsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $title = 'Notifications';
    protected static string $view = 'filament.pages.notifications-page';

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }
        $count = Notification::where(fn (Builder $q) => $q->where('recipient_user_id', $user->id)->orWhere('recipient_email', $user->email))
            ->whereNull('read_at')
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'info';
    }

    public function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->query(
                Notification::query()
                    ->where(fn (Builder $q) => $q->where('recipient_user_id', $user->id)->orWhere('recipient_email', $user->email))
                    ->latest('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->limit(60)
                    ->searchable()
                    ->weight(fn (Notification $record) => $record->read_at ? null : 'bold'),
                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'email' => 'info',
                        'teams' => 'purple',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('notification_category')
                    ->label('Category')
                    ->formatStateUsing(fn (?string $state) => $state ? ucwords(str_replace('_', ' ', $state)) : '—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'sent' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'skipped' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Received')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('read_at')
                    ->label('Read')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Unread'),
            ])
            ->filters([
                Tables\Filters\Filter::make('unread')
                    ->label('Unread Only')
                    ->query(fn (Builder $query) => $query->whereNull('read_at'))
                    ->default(),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_read')
                    ->label('Mark Read')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Notification $record) => $record->read_at === null)
                    ->action(fn (Notification $record) => app(NotificationService::class)->markRead($record->id, auth()->user())),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('mark_all_read')
                    ->label('Mark All Read')
                    ->icon('heroicon-o-check-badge')
                    ->requiresConfirmation()
                    ->action(fn () => app(NotificationService::class)->markAllRead(auth()->user())),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50]);
    }
}
