<?php

namespace App\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Auth\Login as BaseLogin;
use Filament\Support\Enums\IconPosition;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;

/**
 * Halaman login panel admin (dipakai staf dan klien; klien diarahkan ke /klien
 * setelah masuk oleh RedirectToProperPanelMiddleware).
 *
 * Semua logika masuk tetap milik Filament: rate limit 5 percobaan, cek
 * canAccessPanel(), regenerasi sesi, "ingat saya". Kelas ini hanya mengganti
 * tampilan dan teks.
 *
 * Sengaja di app/Filament/Auth, bukan app/Filament/Pages: folder Pages dipindai
 * discoverPages(), yang akan mendaftarkan kelas ini juga sebagai halaman biasa
 * lengkap dengan menu di sidebar.
 */
class Login extends BaseLogin
{
    /** Layout dasar Filament tanpa kartu tengah "simple", supaya foto bisa penuh layar. */
    protected static string $layout = 'filament-panels::components.layout.base';

    protected static string $view = 'filament.auth.login';

    public function getTitle(): string|Htmlable
    {
        return 'Masuk';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Masuk';
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('Email')
            ->placeholder('nama@perusahaan.co.id')
            ->prefixIcon('heroicon-m-envelope')
            ->inlinePrefix()
            ->email()
            ->required()
            // Both fields are obviously required; the asterisk is just noise.
            ->markAsRequired(false)
            ->autocomplete('username')
            ->autofocus()
            ->validationMessages([
                'required' => 'Email wajib diisi.',
                'email' => 'Format email tidak valid.',
            ])
            ->extraInputAttributes(['tabindex' => 1]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Kata sandi')
            ->placeholder('Masukkan kata sandi')
            ->prefixIcon('heroicon-m-lock-closed')
            ->inlinePrefix()
            ->inlineSuffix()
            ->password()
            ->revealable(filament()->arePasswordsRevealable())
            ->autocomplete('current-password')
            ->required()
            ->markAsRequired(false)
            ->validationMessages([
                'required' => 'Kata sandi wajib diisi.',
            ])
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Ingat saya di perangkat ini');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('Masuk')
            ->icon('heroicon-m-arrow-right')
            ->iconPosition(IconPosition::After)
            ->submit('authenticate');
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.email' => 'Email atau kata sandi tidak cocok.',
        ]);
    }

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        return Notification::make()
            ->title('Terlalu banyak percobaan masuk')
            ->body("Coba lagi dalam {$exception->secondsUntilAvailable} detik.")
            ->danger();
    }
}
