<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContractResource\Pages;
use App\Models\Contract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('region_id')->relationship('region', 'name')->required()->searchable()->reactive(),
            Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->required()->searchable()->reactive(),
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->required()->searchable(),
            Forms\Components\Select::make('counterparty_id')->relationship('counterparty', 'legal_name')->required()->searchable(),
            Forms\Components\Select::make('contract_type')->options(['Commercial' => 'Commercial', 'Merchant' => 'Merchant'])->required(),
            Forms\Components\TextInput::make('title')->maxLength(255),
            Forms\Components\FileUpload::make('storage_path')->label('Contract File')->disk('s3')->directory('contracts')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                ->afterStateUpdated(function ($state, Set $set) {
                    if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                        $set('file_name', $state->getClientOriginalName());
                    }
                }),
            Forms\Components\Hidden::make('file_name'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('title')->searchable()->sortable()->limit(40),
            Tables\Columns\BadgeColumn::make('contract_type'),
            Tables\Columns\BadgeColumn::make('workflow_state')->colors([
                'gray' => 'draft', 'warning' => 'review', 'info' => 'approval',
                'primary' => 'signing', 'success' => 'executed', 'secondary' => 'archived',
            ]),
            Tables\Columns\TextColumn::make('counterparty.legal_name')->sortable()->limit(30),
            Tables\Columns\TextColumn::make('region.name')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('contract_type')->options(['Commercial' => 'Commercial', 'Merchant' => 'Merchant']),
            Tables\Filters\SelectFilter::make('workflow_state')->options([
                'draft' => 'Draft', 'review' => 'Review', 'approval' => 'Approval',
                'signing' => 'Signing', 'executed' => 'Executed', 'archived' => 'Archived',
            ]),
            Tables\Filters\SelectFilter::make('region_id')->relationship('region', 'name'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('download')->icon('heroicon-o-arrow-down-tray')
                ->url(fn (Contract $record) => $record->storage_path ? route('contract.download', $record) : null)
                ->visible(fn (Contract $record) => (bool) $record->storage_path),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContracts::route('/'),
            'create' => Pages\CreateContract::route('/create'),
            'edit' => Pages\EditContract::route('/{record}/edit'),
        ];
    }
}
