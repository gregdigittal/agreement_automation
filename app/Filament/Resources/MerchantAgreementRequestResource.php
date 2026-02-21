<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MerchantAgreementRequestResource\Pages;
use App\Models\MerchantAgreement;
use App\Services\MerchantAgreementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MerchantAgreementRequestResource extends Resource
{
    protected static ?string $model = MerchantAgreement::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Generate Merchant Agreement';
    protected static ?string $modelLabel = 'Merchant Agreement Request';
    protected static ?string $pluralModelLabel = 'Merchant Agreement Requests';
    protected static ?string $slug = 'merchant-agreement-requests';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('counterparty_id')->relationship('counterparty', 'legal_name')->required()->searchable(),
            Forms\Components\Select::make('region_id')->relationship('region', 'name')->required()->searchable()->reactive(),
            Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->required()->searchable()->reactive(),
            Forms\Components\Select::make('project_id')->relationship('project', 'name')->required()->searchable(),
            Forms\Components\TextInput::make('merchant_fee')->numeric()->prefix('$'),
            Forms\Components\Textarea::make('region_terms')->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('counterparty.legal_name')->sortable()->searchable(),
            Tables\Columns\TextColumn::make('region.name')->sortable(),
            Tables\Columns\TextColumn::make('entity.name')->sortable(),
            Tables\Columns\TextColumn::make('project.name')->sortable(),
            Tables\Columns\TextColumn::make('merchant_fee')->money('USD'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->filters([])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('generate_docx')
                ->label('Generate Agreement')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generate Merchant Agreement DOCX')
                ->modalDescription('This will download the master template from S3, fill placeholders, and create a contract record. Proceed?')
                ->action(function (MerchantAgreement $record) {
                    try {
                        $contract = app(MerchantAgreementService::class)->generateFromAgreement($record, auth()->user());
                        \Filament\Notifications\Notification::make()
                            ->title('Agreement generated successfully')
                            ->body("Contract ID: {$contract->id}")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Generation failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMerchantAgreementRequests::route('/'),
            'create' => Pages\CreateMerchantAgreementRequest::route('/create'),
            'edit' => Pages\EditMerchantAgreementRequest::route('/{record}/edit'),
        ];
    }
}
