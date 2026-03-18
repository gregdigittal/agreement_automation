<?php

declare(strict_types=1);

namespace App\Filament\Platform\Resources;

use App\Filament\Platform\Resources\TenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Tenants';

    protected static ?string $modelLabel = 'Tenant';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Tenant Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Organisation Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->alphaDash()
                            ->lowercase()
                            ->maxLength(63)
                            ->helperText('Lowercase letters, numbers, and hyphens. Used in the tenant domain.')
                            ->unique(ignoreRecord: true),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                Tenant::STATUS_ACTIVE => 'Active',
                                Tenant::STATUS_PROVISIONING => 'Provisioning',
                                Tenant::STATUS_SUSPENDED => 'Suspended',
                            ])
                            ->default(Tenant::STATUS_ACTIVE)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Organisation')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (Tenant $record): string => $record->status ?? '')
                    ->colors([
                        'success' => Tenant::STATUS_ACTIVE,
                        'warning' => Tenant::STATUS_PROVISIONING,
                        'danger' => Tenant::STATUS_SUSPENDED,
                    ]),

                Tables\Columns\TextColumn::make('domains_count')
                    ->label('Domains')
                    ->counts('domains')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Tenant::STATUS_ACTIVE => 'Active',
                        Tenant::STATUS_PROVISIONING => 'Provisioning',
                        Tenant::STATUS_SUSPENDED => 'Suspended',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record): bool => $record->status === Tenant::STATUS_ACTIVE)
                    ->action(fn (Tenant $record) => $record->update(['status' => Tenant::STATUS_SUSPENDED])),

                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Tenant $record): bool => $record->status === Tenant::STATUS_SUSPENDED)
                    ->action(fn (Tenant $record) => $record->update(['status' => Tenant::STATUS_ACTIVE])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
