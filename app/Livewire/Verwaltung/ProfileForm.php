<?php

namespace App\Livewire\Verwaltung;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class ProfileForm extends Component
{
    // Profile
    public string $name = '';
    public string $email = '';
    public string $publicName = '';

    // Password
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirmation = '';

    // 2FA
    public bool $twoFactorEnabled = false;

    // UI State
    public bool $saved = false;
    public bool $passwordChanged = false;
    public string $activeTab = 'profile';

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->publicName = $user->public_name ?? '';
        $this->twoFactorEnabled = $user->hasTwoFactorEnabled();
    }

    protected function profileRules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:central.users,email,' . Auth::id()],
            'publicName' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function passwordRules(): array
    {
        return [
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', Password::min(8)->mixedCase()->numbers(), 'confirmed:newPasswordConfirmation'],
            'newPasswordConfirmation' => ['required', 'string'],
        ];
    }

    public function saveProfile(): void
    {
        $this->validate($this->profileRules());

        $user = Auth::user();

        $user->update([
            'name' => $this->name,
            'email' => $this->email,
            'public_name' => $this->publicName ?: null,
        ]);

        $this->saved = true;
        $this->dispatch('toast', type: 'success', message: 'Profil gespeichert');
    }

    public function changePassword(): void
    {
        $this->validate($this->passwordRules());

        $user = Auth::user();

        if (! Hash::check($this->currentPassword, $user->password)) {
            $this->addError('currentPassword', 'Das aktuelle Passwort ist falsch.');
            return;
        }

        $user->update([
            'password' => Hash::make($this->newPassword),
        ]);

        $this->currentPassword = '';
        $this->newPassword = '';
        $this->newPasswordConfirmation = '';

        $this->passwordChanged = true;
        $this->dispatch('toast', type: 'success', message: 'Passwort geändert');
    }

    public function render()
    {
        return view('livewire.verwaltung.profile-form', [
            'twoFactorAuthEnabled' => config('app.two_factor_auth_enabled', false),
        ]);
    }
}
