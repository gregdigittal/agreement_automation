<?php

namespace App\Filament\Vendor\Resources;

use App\Filament\Vendor\Resources\VendorContractResource\Pages;
use App\Models\Contract;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'My Contracts';
    protected static ?string $slug = 'contracts';

    public static function canCreate(): bool { return false; }

    public static function getEloquentQuery(): Builder
    {
        $user = auth('vendor')->user();
        return parent::getEloquentQuery()
            ->where('counterparty_id', $user?->counterparty_id);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
            Tables\Columns\BadgeColumn::make('contract_type'),
            Tables\Columns\BadgeColumn::make('workflow_state')->colors([
                'gray' => 'draft', 'success' => 'executed',
            ]),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->actions([
            Tables\Actions\Action::make('download')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (Contract $record) => $record->storage_path
                    ? route('vendor.contract.download', $record)
                    : null)
                ->visible(fn (Contract $record) => (bool) $record->storage_path),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorContracts::route('/'),
        ];
    }
}
