<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationGroup = 'Administration';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Users';
    protected static ?int $navigationSort = 55;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('system_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        $roleOptions = Role::where('guard_name', 'web')
            ->pluck('name', 'name')
            ->toArray();

        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            Forms\Components\Select::make('roles')
                ->label('Roles')
                ->multiple()
                ->options($roleOptions)
                ->required()
                ->helperText('Assign one or more roles to this user.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->label('Roles'),
                Tables\Columns\TextColumn::make('created_at')->since()->label('Created'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Select::make('roles')
                            ->label('Assign Roles')
                            ->multiple()
                            ->options(
                                Role::where('guard_name', 'web')
                                    ->pluck('name', 'name')
                                    ->toArray()
                            )
                            ->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update(['status' => 'active']);
                        $record->syncRoles($data['roles']);

                        \Illuminate\Support\Facades\Mail::to($record->email)
                            ->send(new \App\Mail\UserApprovedMail($record, $data['roles']));

                        \Filament\Notifications\Notification::make()
                            ->title("Approved {$record->name}")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record) => $record->status === 'active' && !$record->is(auth()->user()))
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update(['status' => 'suspended'])),
                Tables\Actions\Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (User $record) => $record->status === 'suspended')
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update(['status' => 'active'])),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (User $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalDescription('This will delete the pending user. They can SSO again to re-enter the queue.')
                    ->action(fn (User $record) => $record->delete()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
