<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Steps;

use App\Companies\Deletion\DeletionContext;
use App\Companies\Deletion\DeletionStep;
use App\Companies\Deletion\StepResult;
use App\Models\Portal\Company;

/**
 * Schritt 1: Profil stilllegen (#8).
 *
 * Soft Delete, nicht Hard Delete — eine Fehlbedienung im Panel soll 30 Tage
 * rueckholbar bleiben (`companies.hard_delete_after`, Scheduler
 * `companies:hard-delete-due`). Fuer Art. 17 ist der Soft Delete allein
 * nicht genug; genau darum setzt dieser Schritt die Frist, nach der das
 * harte Loeschen laeuft, und alle uebrigen Schritte dieser Pipeline
 * loeschen sofort und endgueltig.
 *
 * Zwei Spalten muessen dabei freigegeben werden: `slug` und
 * `google_places_id` tragen einen Unique-Index, den ein soft-geloeschter
 * Datensatz weiter belegt. Ohne Freigabe koennte derselbe Betrieb sich nicht
 * neu eintragen. Die Originalwerte wandern — zusammen mit `is_active` —
 * nach `pre_deletion_json`, damit ein Zurueckholen sie wiederherstellen kann
 * ({@see App\Companies\Deletion\CompanyRestoration}).
 */
class SoftDeleteProfile implements DeletionStep
{
    public const SUFFIX = '-deleted-';

    public function key(): string
    {
        return 'soft_delete_profile';
    }

    public function label(): string
    {
        return 'Profil stilllegen';
    }

    public function plan(DeletionContext $context): string
    {
        $days = $this->retentionDays();

        return sprintf(
            'Profil #%d "%s" auf is_active=false setzen, soft-deleten, Slug und Google-Places-ID freigeben, hartes Löschen ab %s vormerken.',
            (int) $context->company->getKey(),
            (string) $context->company->name,
            now()->addDays($days)->format('d.m.Y'),
        );
    }

    public function run(DeletionContext $context): StepResult
    {
        $company = $context->company;

        if ($company->trashed()) {
            return StepResult::skipped('Profil war schon soft-deleted.', [
                'deleted_at' => $company->deleted_at?->toIso8601String(),
            ]);
        }

        $original = [
            'slug' => (string) $company->slug,
            'google_places_id' => $company->google_places_id,
            'is_active' => (bool) $company->is_active,
        ];

        $company->forceFill([
            'is_active' => false,
            'deleted_reason' => $context->reason,
            'hard_delete_after' => now()->addDays($this->retentionDays()),
            'pre_deletion_json' => $original,
            'slug' => $this->freeSlug($company),
            'google_places_id' => null,
        ])->save();

        $company->delete();

        return StepResult::ok('Profil stillgelegt und soft-deleted.', [
            'hard_delete_after' => $company->hard_delete_after?->toIso8601String(),
            'slug_released' => $original['slug'],
        ]);
    }

    /**
     * Freier, aber sprechender Slug: `malerbetrieb-mueller-deleted-123`. Die
     * Profil-URL steckt ohnehin in `gone_urls`, der Slug muss hier nur
     * eindeutig und lesbar sein.
     */
    private function freeSlug(Company $company): string
    {
        $candidate = $company->slug.self::SUFFIX.$company->getKey();

        return mb_substr($candidate, 0, 191);
    }

    private function retentionDays(): int
    {
        return max(1, (int) config('profile-requests.deletion.hard_delete_after_days', 30));
    }
}
