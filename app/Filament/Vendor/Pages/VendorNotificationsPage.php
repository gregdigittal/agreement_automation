<?php
namespace App\Filament\Vendor\Pages;

use App\Models\VendorNotification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class VendorNotificationsPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Notifications';
    protected static string $view = 'filament.vendor.pages.notifications';
    protected static ?int $navigationSort = 10;

    public function table(Table $table): Table
    {
        return $table->query(VendorNotification::where('vendor_user_id', auth('vendor')->id())->latest())
            ->columns([
                Tables\Columns\IconColumn::make('read_at')->label('')->boolean()->trueIcon('heroicon-o-check-circle')->falseIcon('heroicon-o-ellipsis-horizontal-circle')->trueColor('success')->falseColor('warning'),
                Tables\Columns\TextColumn::make('subject')->weight(fn ($record) => $record->isRead() ? 'normal' : 'bold'),
                Tables\Columns\TextColumn::make('body')->limit(60)->wrap(),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->actions([
                Tables\Actions\Action::make('mark_read')->label('Mark Read')->icon('heroicon-o-check')
                    ->visible(fn (VendorNotification $record) => !$record->isRead())
                    ->action(fn (VendorNotification $record) => $record->markRead()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('mark_all_read')->label('Mark All Read')
                    ->action(fn () => VendorNotification::where('vendor_user_id', auth('vendor')->id())->whereNull('read_at')->update(['read_at' => now()])),
            ]);
    }

    public function getUnreadCount(): int
    {
        return VendorNotification::where('vendor_user_id', auth('vendor')->id())->whereNull('read_at')->count();
    }
}
