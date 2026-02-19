<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WikiContractResource\Pages;
use App\Models\WikiContract;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WikiContractResource extends Resource
{
    protected static ?string $model = WikiContract::class;
    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?string $navigationGroup = 'Contracts';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\TextInput::make('category')->maxLength(255),
            Forms\Components\Select::make('region_id')->relationship('region', 'name')->searchable(),
            Forms\Components\Textarea::make('description')->rows(4),
            Forms\Components\Select::make('status')->options(['active' => 'Active', 'draft' => 'Draft', 'archived' => 'Archived'])->default('draft'),
            Forms\Components\FileUpload::make('file_path')->label('Template File')->disk('s3')->directory('wiki-contracts')
                ->acceptedFileTypes(['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('category')->sortable(),
            Tables\Columns\BadgeColumn::make('status')->colors(['success' => 'active', 'gray' => 'draft', 'secondary' => 'archived']),
            Tables\Columns\TextColumn::make('version')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWikiContracts::route('/'),
            'create' => Pages\CreateWikiContract::route('/create'),
            'edit' => Pages\EditWikiContract::route('/{record}/edit'),
        ];
    }
}
