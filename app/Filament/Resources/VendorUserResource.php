<?php
namespace App\Filament\Resources;

use App\Filament\Resources\VendorUserResource\Pages;
use App\Models\VendorUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class VendorUserResource extends Resource
{
    protected static ?string $model = VendorUser::class;
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Vendor Users';
    protected static ?int $navigationSort = 60;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('system_admin');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->required()->maxLength(255)
                ->helperText('Full name of the vendor portal user.'),
            Forms\Components\TextInput::make('email')->email()->required()->unique(ignoreRecord: true)
                ->helperText('Email address used for magic link login to the vendor portal.'),
            Forms\Components\Select::make('counterparty_id')->label('Counterparty')->relationship('counterparty', 'legal_name')->searchable()->preload()->required()
                ->helperText('The counterparty organisation this user belongs to.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('email')->searchable(),
            Tables\Columns\TextColumn::make('counterparty.legal_name')->label('Counterparty')->searchable(),
            Tables\Columns\TextColumn::make('last_login_at')->label('Last Login')->since()->default('Never'),
            Tables\Columns\TextColumn::make('created_at')->since(),
        ])->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\Action::make('send_invite')
                ->label('Send Login Link')
                ->icon('heroicon-o-envelope')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (VendorUser $record) {
                    $token = \Illuminate\Support\Str::random(64);
                    \App\Models\VendorLoginToken::create([
                        'id' => \Illuminate\Support\Str::uuid()->toString(),
                        'vendor_user_id' => $record->id,
                        'token_hash' => hash('sha256', $token),
                        'expires_at' => now()->addHours(48),
                        'created_at' => now(),
                    ]);
                    $link = route('vendor.auth.verify', ['token' => $token]);
                    \Illuminate\Support\Facades\Mail::to($record->email)
                        ->send(new \App\Mail\VendorMagicLink($record, $link));
                    \Filament\Notifications\Notification::make()
                        ->title('Login link sent to ' . $record->email)
                        ->success()
                        ->send();
                }),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVendorUsers::route('/'),
            'create' => Pages\CreateVendorUser::route('/create'),
            'edit' => Pages\EditVendorUser::route('/{record}/edit'),
        ];
    }
}
