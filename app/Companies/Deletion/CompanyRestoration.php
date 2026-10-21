<?php

declare(strict_types=1);

namespace App\Companies\Deletion;

use App\Models\Portal\Company;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Nachlese zum Zurueckholen eines soft-geloeschten Profils (#8).
 *
 * Der Soft Delete der Pipeline gibt `slug` und `google_places_id` frei (beide
 * tragen einen Unique-Index, den ein geloeschter Datensatz sonst weiter
 * belegt) und legt die Originalwerte in `pre_deletion_json` ab. Wer das
 * Profil innerhalb der 30 Tage zurueckholt, braucht sie zurueck — sonst
 * stehen Portal und Suchmaschinen vor einer URL wie
 * `/4711-malerbetrieb-mueller-deleted-4711`.
 *
 * Und die 410-Regel muss weg, sonst liefert das zurueckgeholte Profil
 * weiterhin „dauerhaft geloescht".
 *
 * Nicht zurueckgeholt werden Bilder, Bewertungen und Statistiken: die hat
 * die Pipeline endgueltig geloescht. Das Zurueckholen rettet den Eintrag,
 * nicht seinen Inhalt.
 */
class CompanyRestoration
{
    public function __construct(private readonly GoneUrlRegistry $registry) {}

    public function afterRestore(Company $company): void
    {
        try {
            $original = (array) ($company->pre_deletion_json ?? []);

            $restore = [
                'deleted_reason' => null,
                'hard_delete_after' => null,
                'pre_deletion_json' => null,
            ];

            if (isset($original['slug']) && $this->slugIsFree((string) $original['slug'], $company)) {
                $restore['slug'] = (string) $original['slug'];
            }

            if (isset($original['google_places_id']) && $original['google_places_id'] !== null) {
                $restore['google_places_id'] = $original['google_places_id'];
            }

            // Ohne `is_active` waere das zurueckgeholte Profil weiter
            // unsichtbar (CompanyController::show prueft die Spalte) — eine
            // Fehlbedienung waere dann nur halb rueckgaengig gemacht.
            if (array_key_exists('is_active', $original)) {
                $restore['is_active'] = (bool) $original['is_active'];
            }

            $company->forceFill($restore)->saveQuietly();

            $this->registry->releaseForCompany($company);
        } catch (Throwable $exception) {
            Log::error('Loeschpipeline: Zurueckholen nicht vollstaendig.', [
                'company_id' => $company->getKey(),
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Hat sich in der Zwischenzeit ein anderer Betrieb den Slug geholt,
     * bleibt der Eintrag beim Suffix-Slug — ein Unique-Fehler wuerde das
     * Zurueckholen ganz verhindern.
     */
    private function slugIsFree(string $slug, Company $company): bool
    {
        return ! Company::withTrashed()
            ->where('slug', $slug)
            ->whereKeyNot($company->getKey())
            ->exists();
    }
}
