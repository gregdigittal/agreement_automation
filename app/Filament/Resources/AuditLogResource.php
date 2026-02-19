<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationGroup = 'Admin';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('created_at')->label('At')->dateTime()->sortable(),
            Tables\Columns\TextColumn::make('action')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('resource_type')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('resource_id')->sortable(),
            Tables\Columns\TextColumn::make('actor_email')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('ip_address')->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('action')->options([
                'create' => 'Create',
                'update' => 'Update',
                'delete' => 'Delete',
                'upload' => 'Upload',
                'download' => 'Download',
                'state_change' => 'State Change',
            ]),
        ])
        ->defaultSort('created_at', 'desc')
        ->actions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
