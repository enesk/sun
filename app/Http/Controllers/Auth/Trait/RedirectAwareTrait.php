<?php

namespace App\Http\Controllers\Auth\Trait;

use App\Models\User;
use Illuminate\Support\Facades\Redirect;

trait RedirectAwareTrait
{
    protected function getRedirectUrl(?User $user): string
    {
        if (! $user) {
            return route('home');
        }

        if (Redirect::getIntendedUrl() !== null && rtrim(Redirect::getIntendedUrl(), '/') !== rtrim((route('home')), '/')) {
            return Redirect::getIntendedUrl();
        }

        // Im Tenant-Kontext: Firmeninhaber ins Tenant-Dashboard leiten
        if (tenant()) {
            return '/dashboard';
        }

        if ($user->is_admin) {
            return route('filament.admin.pages.dashboard');
        }

        return route('dashboard');
    }
}
