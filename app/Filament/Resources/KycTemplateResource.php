<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KycTemplateResource\Pages;
use App\Models\KycTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class KycTemplateResource extends Resource
{
    protected static ?string $model = KycTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Counterparties';
    protected static ?int $navigationSort = 5;
    protected static ?string $navigationLabel = 'KYC Templates';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Template Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. UAE Commercial KYC')
                    ->helperText('A descriptive name for this KYC checklist template.'),
                Forms\Components\Select::make('entity_id')
                    ->relationship('entity', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('All entities')
                    ->helperText('Restrict this template to a specific entity, or leave blank for all.'),
                Forms\Components\Select::make('jurisdiction_id')
                    ->relationship('jurisdiction', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('All jurisdictions')
                    ->helperText('Restrict this template to a specific jurisdiction, or leave blank for all.'),
                Forms\Components\Select::make('contract_type_pattern')
                    ->options(fn () => array_merge(
                        ['*' => 'All Types (*)'],
                        \App\Models\Contract::query()
                            ->distinct()
                            ->whereNotNull('contract_type')
                            ->pluck('contract_type', 'contract_type')
                            ->toArray()
                    ))
                    ->default('*')
                    ->required()
                    ->helperText('Which contract types this KYC template applies to.'),
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ])
                    ->required()
                    ->default('draft')
                    ->helperText('Only active templates are applied to new contracts.'),
            ])->columns(2),

            Forms\Components\Section::make('Checklist Items')->schema([
                Forms\Components\Repeater::make('items')
                    ->relationship()
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2)
                            ->helperText('Display label shown to the user filling in the checklist.'),
                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->columnSpan(2)
                            ->helperText('Additional instructions or guidance for this checklist item.'),
                        Forms\Components\Select::make('field_type')
                            ->options([
                                'text' => 'Text',
                                'textarea' => 'Textarea',
                                'number' => 'Number',
                                'date' => 'Date',
                                'yes_no' => 'Yes / No',
                                'select' => 'Select (Dropdown)',
                                'file_upload' => 'File Upload',
                                'attestation' => 'Attestation',
                            ])
                            ->required()
                            ->default('text')
                            ->helperText('How this item will be presented on the KYC form.'),
                        Forms\Components\Toggle::make('is_required')
                            ->default(true)
                            ->helperText('Whether this item must be completed before submission.'),
                        Forms\Components\KeyValue::make('options')
                            ->columnSpan(2)
                            ->visible(fn (Forms\Get $get) => $get('field_type') === 'select')
                            ->helperText('Key-value pairs for dropdown options (key = stored value, value = display label).'),
                    ])
                    ->orderColumn('sort_order')
                    ->collapsible()
                    ->cloneable()
                    ->itemLabel(fn (array $state): ?string => $state['label'] ?? null)
                    ->columns(2)
                    ->defaultItems(0),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('entity.name')
                    ->placeholder('All')
                    ->sortable(),
                Tables\Columns\TextColumn::make('jurisdiction.name')
                    ->placeholder('All')
                    ->sortable(),
                Tables\Columns\TextColumn::make('contract_type_pattern')
                    ->badge()
                    ->label('Type Pattern'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'draft' => 'gray',
                        'archived' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Items')
                    ->sortable(),
                Tables\Columns\TextColumn::make('version')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['system_admin', 'legal']) ?? false;
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
            'index' => Pages\ListKycTemplates::route('/'),
            'create' => Pages\CreateKycTemplate::route('/create'),
            'edit' => Pages\EditKycTemplate::route('/{record}/edit'),
        ];
    }
}
