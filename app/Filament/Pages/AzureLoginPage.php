<?php

namespace App\Filament\Pages;

use App\Enums\UserStatus;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Models\Contracts\FilamentUser;
use Filament\Pages\Auth\Login;
use Illuminate\Validation\ValidationException;

class AzureLoginPage extends Login
{
    protected static string $view = 'filament.pages.auth.login';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();

        // Check if user exists and has no password (SSO-only account)
        $user = \App\Models\User::where('email', $data['email'])->first();

        if ($user && $user->password === null) {
            throw ValidationException::withMessages([
                'data.email' => 'This account uses Microsoft SSO. Please click "Sign in with Microsoft" below.',
            ]);
        }

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $this->throwFailureValidationException();
        }

        $user = Filament::auth()->user();

        if ($user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            Filament::auth()->logout();

            $this->throwFailureValidationException();
        }

        session()->regenerate();

        return app(LoginResponse::class);
    }

    public function getAzureRedirectUrl(): string
    {
        return route('azure.redirect');
    }
}
