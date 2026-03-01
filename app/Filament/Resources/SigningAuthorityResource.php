<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SigningAuthorityResource\Pages;
use App\Models\SigningAuthority;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SigningAuthorityResource extends Resource
{
    protected static ?string $model = SigningAuthority::class;
    protected static ?string $navigationIcon = 'heroicon-o-pencil-square';
    protected static ?string $navigationGroup = 'Organization';
    protected static ?int $navigationSort = 13;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('entity_id')
                ->relationship('entity', 'name')
                ->required()
                ->searchable()
                ->preload()
                ->helperText('The entity this signing authority belongs to. Create entities under Organization > Entities.'),
            Forms\Components\Select::make('project_id')
                ->relationship('project', 'name')
                ->searchable()
                ->preload()
                ->helperText('Optionally restrict to a specific project. Create projects under Organization > Projects.'),
            Forms\Components\Select::make('user_id')
                ->label('User')
                ->options(fn () => User::where('status', 'active')
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->toArray())
                ->searchable()
                ->placeholder('Select user (optional)')
                ->live()
                ->afterStateUpdated(function (Forms\Set $set, ?string $state) {
                    if ($state) {
                        $user = User::find($state);
                        if ($user) {
                            $set('user_email', $user->email);
                        }
                    }
                })
                ->helperText('Select an existing CCRS user to link. This auto-fills the email below. Manage users under Administration > Users.'),
            Forms\Components\TextInput::make('user_email')
                ->email()
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. signer@digittal.io')
                ->helperText('Email address of the authorised signatory. Auto-filled when a user is selected above, or enter manually for external signatories.'),
            Forms\Components\TextInput::make('role_or_name')
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. General Counsel, CFO')
                ->helperText('The role or full name of the signing authority.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('entity.name')->sortable(),
            Tables\Columns\TextColumn::make('user_email')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('role_or_name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('project.name')->sortable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
        ])->actions([Tables\Actions\EditAction::make()]);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSigningAuthorities::route('/'),
            'create' => Pages\CreateSigningAuthority::route('/create'),
            'edit' => Pages\EditSigningAuthority::route('/{record}/edit'),
        ];
    }
}
