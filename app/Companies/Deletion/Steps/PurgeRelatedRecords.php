<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Steps;

use App\Companies\Deletion\DeletionContext;
use App\Companies\Deletion\DeletionStep;
use App\Companies\Deletion\StepResult;
use App\Models\Portal\ClaimRequest;
use App\Models\Portal\CompanyDescriptionBackup;
use App\Models\Portal\CompanyEditSuggestion;
use App\Models\Portal\CompanyEvent;
use App\Models\Portal\CompanyOpeningHour;
use App\Models\Portal\CompanyReference;
use App\Models\Portal\CompanyService;
use App\Models\Portal\CompanyStatsDaily;
use App\Models\Portal\CompanyVerification;
use App\Models\Portal\FeaturedPlacement;
use App\Models\Portal\Job;
use App\Models\Portal\JobApplication;
use App\Models\Portal\LeadQuotaUsage;
use App\Models\Portal\ProfileDescriptionRewrite;
use App\Models\Portal\TrackingDailyStat;
use App\Models\Portal\TrackingEvent;
use App\Services\Premium\CompanyVerificationService;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Der Rest der Loesch-Matrix (docs/profile-requests.md 6): alles, was am
 * Profil haengt und keinen eigenen Schritt braucht, weil es ohne
 * Besonderheit geloescht wird (#8).
 *
 * Besonderheiten, die es doch gibt und die deshalb hier stehen:
 *  * **Bewerbungen** werden vor dem Loeschen der Anzeige anonymisiert. Der
 *    Fremdschluessel `job_applications.job_id` loescht kaskadierend mit, die
 *    Anonymisierung ist also die Absicherung fuer den Fall, dass eine
 *    Kaskade ausfaellt — fremde Bewerberdaten sollen unter keinen Umstaenden
 *    als Rest zurueckbleiben.
 *  * **Verifikationsnachweise** liegen als Datei auf der privaten Disk und
 *    nicht in der Media Library; die Datei wird einzeln geloescht.
 *  * **Uebernahmeantraege** tragen Nachweisdokumente in der Media-Collection
 *    `claim_documents`; `ClaimRequest::delete()` raeumt sie ueber die Media
 *    Library ab, ein Massen-Delete wuerde die Dateien liegen lassen.
 *
 * Ein Fehler in einer Datenart haelt die anderen nicht auf; er steht einzeln
 * im Protokoll.
 */
class PurgeRelatedRecords implements DeletionStep
{
    public function __construct(private readonly CompanyVerificationService $verifications) {}

    public function key(): string
    {
        return 'purge_related';
    }

    public function label(): string
    {
        return 'Angehängte Daten löschen';
    }

    public function plan(DeletionContext $context): string
    {
        $counts = [];

        foreach ($this->simpleTargets() as $table => $class) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $count = $class::query()->where('company_id', $context->company->getKey())->count();

            if ($count > 0) {
                $counts[] = "{$table}: {$count}";
            }
        }

        return 'Löschen — '.($counts === [] ? 'keine angehängten Datensätze' : implode(', ', $counts))
            .'; Stellenanzeigen samt anonymisierten Bewerbungen; Verifikationsnachweise samt Datei.';
    }

    public function run(DeletionContext $context): StepResult
    {
        $companyId = (int) $context->company->getKey();
        $deleted = [];
        $errors = [];

        foreach ($this->simpleTargets() as $table => $class) {
            try {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $count = (int) $class::query()->where('company_id', $companyId)->delete();

                if ($count > 0) {
                    $deleted[$table] = $count;
                }
            } catch (Throwable $exception) {
                $errors[$table] = $exception->getMessage();
            }
        }

        try {
            $deleted['job_applications'] = $this->anonymizeApplications($companyId);
            $deleted['jobs'] = (int) Job::query()->where('company_id', $companyId)->delete();
        } catch (Throwable $exception) {
            $errors['jobs'] = $exception->getMessage();
        }

        try {
            $deleted['company_verifications'] = $this->purgeVerifications($companyId);
        } catch (Throwable $exception) {
            $errors['company_verifications'] = $exception->getMessage();
        }

        try {
            $deleted['claim_requests'] = $this->purgeClaimRequests($companyId);
        } catch (Throwable $exception) {
            $errors['claim_requests'] = $exception->getMessage();
        }

        $deleted = array_filter($deleted);

        // Kategoriezuordnung: eigene Tabelle ohne Model.
        try {
            $context->company->categories()->detach();
        } catch (Throwable $exception) {
            $errors['category_company'] = $exception->getMessage();
        }

        $payload = ['deleted' => $deleted];

        if ($errors !== []) {
            return StepResult::failed(
                sprintf('%d Datenart(en) nicht vollständig gelöscht: %s.', count($errors), implode(', ', array_keys($errors))),
                $payload + ['errors' => $errors],
            );
        }

        return StepResult::ok(
            sprintf('%d Datensatz/Datensätze aus %d Tabelle(n) gelöscht.', array_sum($deleted), count($deleted)),
            $payload,
        );
    }

    /**
     * Datenarten, die ueber `company_id` haengen und ohne Nebenwirkung
     * verschwinden. Reihenfolge ist gleichgueltig, Fremdschluessel zeigen
     * alle auf `companies`.
     *
     * @return array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private function simpleTargets(): array
    {
        return [
            'company_opening_hours' => CompanyOpeningHour::class,
            'company_services' => CompanyService::class,
            'company_references' => CompanyReference::class,
            'company_events' => CompanyEvent::class,
            'company_stats_daily' => CompanyStatsDaily::class,
            'tracking_events' => TrackingEvent::class,
            'tracking_daily_stats' => TrackingDailyStat::class,
            'lead_quota_usages' => LeadQuotaUsage::class,
            'featured_placements' => FeaturedPlacement::class,
            'company_description_backups' => CompanyDescriptionBackup::class,
            'profile_description_rewrites' => ProfileDescriptionRewrite::class,
            'company_edit_suggestions' => CompanyEditSuggestion::class,
        ];
    }

    /**
     * Bewerberdaten leeren, bevor die Anzeige fällt.
     */
    private function anonymizeApplications(int $companyId): int
    {
        if (! Schema::hasTable('job_applications')) {
            return 0;
        }

        $jobIds = Job::query()->where('company_id', $companyId)->pluck('id');

        if ($jobIds->isEmpty()) {
            return 0;
        }

        return (int) JobApplication::query()
            ->whereIn('job_id', $jobIds)
            ->update([
                'applicant_name' => null,
                'applicant_email' => null,
                'applicant_phone' => null,
                'message' => null,
                'ip_address' => null,
            ]);
    }

    /**
     * Nachweisdokumente liegen auf der privaten Disk, nicht in der Media
     * Library — ohne diesen Schritt bliebe die Datei stehen.
     */
    private function purgeVerifications(int $companyId): int
    {
        if (! Schema::hasTable('company_verifications')) {
            return 0;
        }

        $count = 0;

        foreach (CompanyVerification::query()->where('company_id', $companyId)->get() as $verification) {
            if ($verification->hasDocument()) {
                $this->verifications->disk()->delete($verification->document_path);
            }

            $verification->delete();
            $count++;
        }

        return $count;
    }

    /**
     * `delete()` je Antrag, damit die Media Library die Dateien der
     * Collection `claim_documents` mitnimmt.
     */
    private function purgeClaimRequests(int $companyId): int
    {
        if (! Schema::hasTable('claim_requests')) {
            return 0;
        }

        $count = 0;

        foreach (ClaimRequest::query()->where('company_id', $companyId)->get() as $claim) {
            $claim->delete();
            $count++;
        }

        return $count;
    }
}
