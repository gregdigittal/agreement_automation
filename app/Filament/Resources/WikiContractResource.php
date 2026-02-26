<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WikiContractResource\Pages;
use App\Models\WikiContract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class WikiContractResource extends Resource
{
    protected static ?string $model = WikiContract::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationGroup = 'Contracts';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. Standard NDA Template')
                ->helperText('A descriptive name for this wiki contract template.'),
            Forms\Components\TextInput::make('category')
                ->maxLength(255)
                ->placeholder('e.g. NDA, MSA, SLA')
                ->helperText('Category to group related templates together.'),
            Forms\Components\Select::make('region_id')
                ->relationship('region', 'name')
                ->searchable()
                ->preload()
                ->helperText('Optionally scope this template to a specific region.'),
            Forms\Components\Textarea::make('description')
                ->rows(4)
                ->helperText('A brief description of when and how this template should be used.'),
            Forms\Components\Select::make('status')
                ->options(['draft' => 'Draft', 'review' => 'Review', 'published' => 'Published', 'deprecated' => 'Deprecated'])
                ->default('draft')
                ->helperText('Only published templates are available for use in contracts.'),
            Forms\Components\FileUpload::make('storage_path')->label('Template File')->disk('s3')->directory('wiki-contracts')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),

            Forms\Components\Section::make('Signing Blocks')
                ->description('Define where signature and initials fields should appear when this template is used for signing. Positions are in mm from the top-left of the page.')
                ->collapsed()
                ->schema([
                    Forms\Components\Repeater::make('signingFields')
                        ->relationship()
                        ->label('Signing Field Definitions')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('field_type')
                                    ->options([
                                        'signature' => 'Signature',
                                        'initials' => 'Initials',
                                        'text' => 'Text',
                                        'date' => 'Date',
                                    ])
                                    ->required(),
                                Forms\Components\Select::make('signer_role')
                                    ->options([
                                        'company' => 'Company (Internal)',
                                        'counterparty' => 'Counterparty',
                                        'witness_1' => 'Witness 1',
                                        'witness_2' => 'Witness 2',
                                    ])
                                    ->required()
                                    ->helperText('Who this field is assigned to.'),
                                Forms\Components\TextInput::make('label')
                                    ->placeholder('e.g. Company Signature')
                                    ->maxLength(255),
                            ]),
                            Forms\Components\Grid::make(5)->schema([
                                Forms\Components\TextInput::make('page_number')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->default(1),
                                Forms\Components\TextInput::make('x_position')
                                    ->label('X (mm)')
                                    ->numeric()
                                    ->required()
                                    ->default(20),
                                Forms\Components\TextInput::make('y_position')
                                    ->label('Y (mm)')
                                    ->numeric()
                                    ->required()
                                    ->default(240),
                                Forms\Components\TextInput::make('width')
                                    ->label('Width (mm)')
                                    ->numeric()
                                    ->required()
                                    ->default(60),
                                Forms\Components\TextInput::make('height')
                                    ->label('Height (mm)')
                                    ->numeric()
                                    ->required()
                                    ->default(20),
                            ]),
                            Forms\Components\Toggle::make('is_required')
                                ->default(true)
                                ->inline(),
                        ])
                        ->defaultItems(0)
                        ->addActionLabel('Add Signing Block')
                        ->collapsible()
                        ->cloneable()
                        ->reorderableWithButtons()
                        ->orderColumn('sort_order'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('category')->sortable(),
            Tables\Columns\TextColumn::make('status')->badge()->color(fn ($state) => match($state) { 'draft' => 'gray', 'review' => 'warning', 'published' => 'success', 'deprecated' => 'danger', default => 'gray' }),
            Tables\Columns\TextColumn::make('version')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()]);
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
            'index' => Pages\ListWikiContracts::route('/'),
            'create' => Pages\CreateWikiContract::route('/create'),
            'edit' => Pages\EditWikiContract::route('/{record}/edit'),
        ];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name ?? $record->id;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Category' => $record->category,
            'Status' => $record->status,
        ];
    }

    public static function getGlobalSearchResultUrl(\Illuminate\Database\Eloquent\Model $record): string
    {
        return \App\Filament\Resources\WikiContractResource::getUrl('edit', ['record' => $record]);
    }

}
