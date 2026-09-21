<?php

namespace App\Http\Controllers\Auth\Trait;

use App\Models\User;
use App\Support\IntendedUrl;
use Illuminate\Support\Facades\Redirect;

trait RedirectAwareTrait
{
    protected function getRedirectUrl(?User $user): string
    {
        if (! $user) {
            return route('home');
        }

        $intended = Redirect::getIntendedUrl();

        if ($intended !== null && rtrim($intended, '/') !== rtrim(route('home'), '/') && IntendedUrl::isOwn($intended)) {
            return $intended;
        }

        // Im Tenant-Kontext: Firmeninhaber ins Firmenprofil-Dashboard leiten
        if (tenant()) {
            return '/firmenprofil';
        }

        if ($user->is_admin) {
            return route('filament.admin.pages.dashboard');
        }

        // Reine Ratgeber-Konten (guide_role ohne is_admin) haben kein
        // Nutzer-Dashboard, sondern nur das Content-Panel (#14).
        if ($user->canAccessContentPanel()) {
            return url(config('content.panel.path', 'content'));
        }

        return route('dashboard');
    }
}
