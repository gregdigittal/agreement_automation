<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MerchantAgreementRequestResource\Pages;
use App\Models\ContractType;
use App\Models\Counterparty;
use App\Models\GoverningLaw;
use App\Models\Jurisdiction;
use App\Models\MerchantAgreement;
use App\Models\WikiContract;
use App\Services\MerchantAgreementService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MerchantAgreementRequestResource extends Resource
{
    protected static ?string $model = MerchantAgreement::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';
    protected static ?string $navigationGroup = 'Contracts';
    protected static ?int $navigationSort = 3;
    protected static ?string $navigationLabel = 'Generate Agreement';
    protected static ?string $modelLabel = 'Agreement Request';
    protected static ?string $pluralModelLabel = 'Agreement Requests';
    protected static ?string $slug = 'agreement-requests';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Agreement Type & Template')
                ->description('Select the type of agreement and an optional template from the agreements wiki.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('agreement_type')
                        ->label('Agreement Type')
                        ->options(ContractType::options())
                        ->required()
                        ->searchable()
                        ->live()
                        ->helperText('The type of agreement to generate.'),

                    Forms\Components\Select::make('wiki_contract_id')
                        ->label('Template')
                        ->relationship('wikiContract', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('Select an existing template from the agreements wiki, or leave blank for the default template.'),
                ]),

            Forms\Components\Section::make('Parties & Entities')
                ->description('Select the counterparties, entity, region and project for this agreement.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('counterparty_id')
                        ->relationship('counterparty', 'legal_name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('The primary counterparty for this agreement.')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('legal_name')->required()->maxLength(255)->placeholder('e.g. Acme Corporation Ltd'),
                            Forms\Components\TextInput::make('registration_number')->maxLength(255),
                            Forms\Components\Select::make('jurisdiction')->options(\App\Models\Country::dropdownOptions())->searchable(),
                            Forms\Components\Select::make('status')->options(['Active' => 'Active'])->default('Active')->required(),
                        ]),

                    Forms\Components\Select::make('additional_counterparty_ids')
                        ->label('Additional Counterparties')
                        ->multiple()
                        ->options(fn () => Counterparty::where('status', 'Active')->orderBy('legal_name')->pluck('legal_name', 'id'))
                        ->searchable()
                        ->helperText('Select additional counterparties if the agreement involves multiple parties.'),

                    Forms\Components\Select::make('region_id')
                        ->relationship('region', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText('Region determines which template and terms are applied.')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required()->maxLength(255)->placeholder('e.g. MENA'),
                            Forms\Components\Select::make('code')->options(\App\Models\Country::dropdownOptions())->required()->searchable(),
                        ]),

                    Forms\Components\Select::make('entity_id')
                        ->relationship('entity', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText('The Digittal entity that will sign this agreement.')
                        ->createOptionForm([
                            Forms\Components\Select::make('region_id')->relationship('region', 'name')->required()->searchable()->preload(),
                            Forms\Components\TextInput::make('name')->required()->maxLength(255)->placeholder('e.g. Digittal UAE'),
                            Forms\Components\TextInput::make('code')->maxLength(50)->placeholder('e.g. DGT-AE'),
                        ]),

                    Forms\Components\Select::make('project_id')
                        ->relationship('project', 'name')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->helperText('The project this agreement will be allocated to.')
                        ->createOptionForm([
                            Forms\Components\Select::make('entity_id')->relationship('entity', 'name')->required()->searchable()->preload(),
                            Forms\Components\TextInput::make('name')->required()->maxLength(255),
                            Forms\Components\TextInput::make('code')->maxLength(50),
                        ]),
                ]),

            Forms\Components\Section::make('Legal Framework')
                ->description('Governing law and jurisdictions applicable to this agreement.')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('governing_law_id')
                        ->label('Governing Law')
                        ->relationship('governingLaw', 'name')
                        ->searchable()
                        ->preload()
                        ->helperText('The legal framework governing this agreement.'),

                    Forms\Components\Select::make('jurisdiction_ids')
                        ->label('Jurisdictions')
                        ->multiple()
                        ->options(fn () => Jurisdiction::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->helperText('Select all applicable jurisdictions. If a jurisdiction is missing, describe it in the description below and you will be prompted to create it.')
                        ->createOptionForm([
                            Forms\Components\TextInput::make('name')->required()->maxLength(255)->placeholder('e.g. Dubai International Financial Centre'),
                            Forms\Components\Select::make('country_code')->options(\App\Models\Country::dropdownOptions())->searchable(),
                            Forms\Components\TextInput::make('regulatory_body')->maxLength(255),
                        ]),
                ]),

            Forms\Components\Section::make('Agreement Details')
                ->description('Commercial terms and natural language description of the agreement.')
                ->schema([
                    Forms\Components\TextInput::make('merchant_fee')
                        ->numeric()
                        ->prefix('$')
                        ->visible(fn (Get $get): bool => strtolower($get('agreement_type') ?? '') === 'merchant')
                        ->helperText('Fee percentage or fixed amount for the merchant agreement.'),

                    Forms\Components\Textarea::make('region_terms')
                        ->rows(3)
                        ->helperText('Any region-specific terms to be inserted into the generated agreement.'),

                    Forms\Components\Textarea::make('description')
                        ->label('Agreement Description (NLP)')
                        ->rows(5)
                        ->helperText('Describe the agreement intent in natural language. Include any new jurisdictions, counterparties, or special terms that are not yet in the system — you will be prompted to create them after saving.')
                        ->placeholder('e.g. This commercial agreement covers payment processing services between Digittal and Acme Corp in the DIFC jurisdiction, with arbitration under LCIA rules...'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('agreement_type')
                ->label('Type')
                ->badge()
                ->sortable(),
            Tables\Columns\TextColumn::make('counterparty.legal_name')->sortable()->searchable()->limit(30),
            Tables\Columns\TextColumn::make('region.name')->sortable(),
            Tables\Columns\TextColumn::make('entity.name')->sortable()->limit(30),
            Tables\Columns\TextColumn::make('project.name')->sortable()->limit(30),
            Tables\Columns\TextColumn::make('wikiContract.name')
                ->label('Template')
                ->limit(20)
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('merchant_fee')->money('USD'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])
        ->filters([
            Tables\Filters\SelectFilter::make('agreement_type')
                ->options(ContractType::options()),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('generate_docx')
                ->label('Generate Agreement')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Generate Agreement Document')
                ->modalDescription(fn (MerchantAgreement $record) => "This will generate a {$record->agreement_type} agreement document using " . ($record->wikiContract?->name ?? 'the default template') . '. Proceed?')
                ->action(function (MerchantAgreement $record) {
                    try {
                        $contract = app(MerchantAgreementService::class)->generateFromAgreement($record, auth()->user());
                        \Filament\Notifications\Notification::make()
                            ->title('Agreement generated successfully')
                            ->body("Contract {$contract->contract_ref} created.")
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

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal', 'commercial']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
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
