<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Form;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;

class NotificationPreferencesPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Notification Preferences';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 40;
    protected static string $view = 'filament.pages.notification-preferences';

    public bool $email_enabled = true;
    public bool $teams_enabled = true;
    public array $workflow_actions = ['email', 'teams'];
    public array $escalations = ['email', 'teams'];
    public array $reminders = ['email'];
    public array $contract_status = ['email'];

    public function mount(): void
    {
        $prefs = auth()->user()->notification_preferences ?? [];

        $this->email_enabled = $prefs['email'] ?? true;
        $this->teams_enabled = $prefs['teams'] ?? true;
        $this->workflow_actions = $prefs['channels']['workflow_actions'] ?? ['email', 'teams'];
        $this->escalations = $prefs['channels']['escalations'] ?? ['email', 'teams'];
        $this->reminders = $prefs['channels']['reminders'] ?? ['email'];
        $this->contract_status = $prefs['channels']['contract_status'] ?? ['email'];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Global Toggles')
                ->description('Master switches for each notification channel.')
                ->schema([
                    Toggle::make('email_enabled')->label('Email notifications')
                        ->helperText('Enable or disable all email notifications.'),
                    Toggle::make('teams_enabled')->label('Microsoft Teams notifications')
                        ->helperText('Enable or disable all Microsoft Teams notifications.'),
                ]),

            Section::make('Per-Category Channels')
                ->description('Choose which channels to use for each notification type.')
                ->schema([
                    CheckboxList::make('workflow_actions')
                        ->label('Workflow Actions (approvals, rejections, rework)')
                        ->options(['email' => 'Email', 'teams' => 'Teams'])
                        ->helperText('Notifications when contracts are approved, rejected, or sent for rework.'),
                    CheckboxList::make('escalations')
                        ->label('SLA Escalations')
                        ->options(['email' => 'Email', 'teams' => 'Teams'])
                        ->helperText('Alerts when workflow stages breach their SLA deadlines.'),
                    CheckboxList::make('reminders')
                        ->label('Key Date Reminders')
                        ->options(['email' => 'Email', 'teams' => 'Teams', 'calendar' => 'Calendar (ICS)'])
                        ->helperText('Advance reminders for upcoming contract dates (expiry, renewal, etc.).'),
                    CheckboxList::make('contract_status')
                        ->label('Contract Status Changes')
                        ->options(['email' => 'Email', 'teams' => 'Teams'])
                        ->helperText('Notifications when contracts move between workflow stages.'),
                ]),
        ]);
    }

    public function save(): void
    {
        auth()->user()->update([
            'notification_preferences' => [
                'email' => $this->email_enabled,
                'teams' => $this->teams_enabled,
                'channels' => [
                    'workflow_actions' => $this->workflow_actions,
                    'escalations' => $this->escalations,
                    'reminders' => $this->reminders,
                    'contract_status' => $this->contract_status,
                ],
            ],
        ]);

        Notification::make()->title('Preferences saved.')->success()->send();
    }
}
