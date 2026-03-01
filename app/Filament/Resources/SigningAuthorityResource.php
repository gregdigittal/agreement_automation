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
                ->createOptionForm([
                    Forms\Components\Select::make('region_id')
                        ->relationship('region', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g. Digittal UAE'),
                    Forms\Components\TextInput::make('code')
                        ->maxLength(50)
                        ->placeholder('e.g. DGT-AE'),
                ])
                ->helperText('The entity this signing authority belongs to.'),

            Forms\Components\Toggle::make('all_projects')
                ->label('All Projects')
                ->default(true)
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(function (Forms\Components\Toggle $component, ?SigningAuthority $record) {
                    // If editing an existing record, derive toggle from pivot: no rows = all projects
                    if ($record) {
                        $component->state($record->projects()->count() === 0);
                    }
                })
                ->helperText('Apply this signing authority to all current and future projects.'),

            Forms\Components\Select::make('projects')
                ->relationship('projects', 'name')
                ->multiple()
                ->preload()
                ->searchable()
                ->visible(fn (Forms\Get $get) => ! $get('all_projects'))
                ->createOptionForm([
                    Forms\Components\Select::make('entity_id')
                        ->relationship('entity', 'name')
                        ->required()
                        ->searchable()
                        ->preload(),
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('code')
                        ->maxLength(50),
                ])
                ->helperText('Select specific projects to restrict this signing authority.'),

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
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique('users', 'email'),
                ])
                ->createOptionUsing(function (array $data): string {
                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'status' => 'active',
                    ]);
                    return $user->id;
                })
                ->helperText('Select an existing CCRS user or create a new one. Auto-fills the email below.'),
            Forms\Components\TextInput::make('user_email')
                ->email()
                ->required()
                ->maxLength(255)
                ->placeholder('e.g. signer@digittal.io')
                ->helperText('Auto-filled when a user is selected above, or enter manually for external signatories.'),
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
            Tables\Columns\TextColumn::make('projects.name')
                ->label('Projects')
                ->badge()
                ->default('All Projects')
                ->getStateUsing(function (SigningAuthority $record): string {
                    $names = $record->projects->pluck('name')->toArray();
                    return empty($names) ? 'All Projects' : implode(', ', $names);
                }),
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
