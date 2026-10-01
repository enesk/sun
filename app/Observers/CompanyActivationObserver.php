<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\Company\CompanyActivated;
use App\Models\Portal\Company;
use App\Models\Tenant;

/**
 * Freischaltung eines Eintrags (#Freischalt-Mail): sobald is_active von false
 * auf true wechselt — egal ob ueber die Verwaltung, den Eintrag-Wizard oder
 * Filament —, bekommt der hinterlegte Inhaber eine Mail.
 *
 * Bewusst am Model und nicht in den einzelnen Oberflaechen, weil es mehrere
 * Freischaltwege gibt. Eintraege ohne Inhaber (Google-Importe) loesen nichts
 * aus; der Merker activation_notified_at verhindert Wiederholungen.
 */
class CompanyActivationObserver
{
    public function saved(Company $company): void
    {
        if (! $company->is_active || $company->activation_notified_at !== null) {
            return;
        }

        if (! $company->wasRecentlyCreated && ! $company->wasChanged('is_active')) {
            return;
        }

        /** @var \App\Models\User|null $owner */
        $owner = $company->owner;

        if ($owner === null || ! filter_var((string) $owner->email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        // Merker vor dem Event setzen: saveQuietly loest keine Observer aus,
        // parallele Speichervorgaenge koennen die Mail so nicht verdoppeln.
        $company->forceFill(['activation_notified_at' => now()])->saveQuietly();

        $tenant = tenant();

        CompanyActivated::dispatch($company, $tenant instanceof Tenant ? $tenant : null);
    }
}
