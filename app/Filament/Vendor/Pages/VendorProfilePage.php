<?php
namespace App\Filament\Vendor\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class VendorProfilePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static ?string $navigationLabel = 'My Profile';
    protected static string $view = 'filament.vendor.pages.profile';
    protected static ?int $navigationSort = 90;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['name' => auth('vendor')->user()?->name, 'email' => auth('vendor')->user()?->email]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->label('Full Name')->required()->maxLength(255),
            TextInput::make('email')->label('Email Address')->email()->disabled()->helperText('Email cannot be changed.'),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        auth('vendor')->user()->update(['name' => $data['name']]);
        Notification::make()->title('Profile updated')->success()->send();
    }
}
