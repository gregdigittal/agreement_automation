<?php

namespace App\Filament\Resources\ContractResource\RelationManagers;

use App\Helpers\Feature;
use App\Models\SigningSession;
use App\Services\SigningService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class SigningSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'signingSessions';
    protected static ?string $title = 'Signing Sessions';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return Feature::inHouseSigning();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('status')->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'warning',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('signing_order')->badge(),
                Tables\Columns\TextColumn::make('signers_count')->counts('signers')->label('Signers'),
                Tables\Columns\TextColumn::make('initiator.name')->label('Initiated By'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
                Tables\Columns\TextColumn::make('completed_at')->dateTime()->placeholder('-'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_signers')
                    ->icon('heroicon-o-users')
                    ->modalContent(fn (SigningSession $record) => view('filament.modals.signing-session-signers', ['session' => $record->load('signers', 'auditLog')]))
                    ->modalHeading('Signing Session Details')
                    ->modalSubmitAction(false),
                Tables\Actions\Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (SigningSession $record) => $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function (SigningSession $record) {
                        app(SigningService::class)->cancelSession($record);
                        Notification::make()->success()->title('Session cancelled')->send();
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('send_for_signing')
                    ->label('Send for Signing')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn () => Feature::inHouseSigning())
                    ->form([
                        Forms\Components\Select::make('signing_order')
                            ->options(['sequential' => 'Sequential', 'parallel' => 'Parallel'])
                            ->default('sequential')
                            ->required()
                            ->helperText('Whether signers sign in sequence or all at once.'),
                        Forms\Components\Repeater::make('signers')
                            ->schema([
                                Forms\Components\TextInput::make('name')->required()
                                    ->helperText('Full name of the signer.'),
                                Forms\Components\TextInput::make('email')->email()->required()
                                    ->helperText('Email address for signing invitation.'),
                                Forms\Components\Select::make('type')
                                    ->options(['internal' => 'Internal', 'external' => 'External'])
                                    ->default('external')
                                    ->helperText('Role in the signing process (company or counterparty).'),
                            ])
                            ->minItems(1)
                            ->columns(3),
                    ])
                    ->action(function (array $data) {
                        $contract = $this->getOwnerRecord();
                        $service = app(SigningService::class);

                        $signers = collect($data['signers'])->map(fn ($s, $i) => [
                            'name' => $s['name'],
                            'email' => $s['email'],
                            'type' => $s['type'] ?? 'external',
                            'order' => $i,
                        ])->toArray();

                        $session = $service->createSession($contract, $signers, $data['signing_order']);

                        if ($data['signing_order'] === 'sequential') {
                            $service->sendToSigner($session->signers->first());
                        } else {
                            foreach ($session->signers as $signer) {
                                $service->sendToSigner($signer);
                            }
                        }

                        Notification::make()->success()->title('Signing session created and invitations sent')->send();
                    }),
            ]);
    }
}
