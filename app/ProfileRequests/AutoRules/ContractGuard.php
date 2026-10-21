<?php

declare(strict_types=1);

namespace App\ProfileRequests\AutoRules;

use App\Constants\CompanyLeadStatus;
use App\Constants\SubscriptionStatus;
use App\Models\CompanySubscription;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyLead;
use App\Models\Subscription;
use App\ProfileRequests\Models\ProfileRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Betriebe, die nie automatisch geloescht werden (SUN-RQ #9).
 *
 * Eine automatische Loeschung ist der einzige Vorgang in der Inbox, der sich
 * nicht zurueckdrehen laesst. Drei Dinge machen sie deshalb zur Handarbeit:
 *
 *  1. **Laufender Premium-Vertrag** — `companies.is_premium` oder eine
 *     Subscription in `active`/`past_due` ueber `company_subscriptions`. Vor
 *     der Loeschung gehoert der Vertrag beendet, sonst laeuft die
 *     Abrechnung gegen ein Profil, das es nicht mehr gibt.
 *  2. **Offene Leads** — eine exklusive Anfrage in `new` oder `contacted`
 *     ist bezahlte Leistung, die noch nicht erbracht ist.
 *  3. **Markierung „manuell pruefen"** aus
 *     `profile-requests.auto.manual_review`.
 *
 * Greift einer der Punkte, bleibt die Anfrage auf `verified` und wartet im
 * Panel (#7) — abgelehnt wird sie nicht: der Loeschwunsch ist berechtigt,
 * nur eben nicht unbesehen ausfuehrbar.
 *
 * Die Pruefung laeuft **zweimal**: beim Start der Karenzfrist und erneut
 * direkt vor der Loeschung ({@see \App\Console\Commands\RequestsRunGraceDeletions}).
 * In 48 Stunden kann ein Vertrag entstehen.
 */
class ContractGuard
{
    /**
     * Grund, warum nicht automatisch geloescht werden darf — oder null,
     * wenn nichts dagegen spricht.
     *
     * Ein Fehler bei der Pruefung ist selbst ein Grund: ohne gelesene
     * Vertragslage wird nicht geloescht.
     */
    public function blockingReason(ProfileRequest $request): ?string
    {
        if ($request->tenant_id === null || $request->company_id === null) {
            return 'ohne Portal oder Betrieb';
        }

        if ($this->isMarkedForManualReview((int) $request->tenant_id, (int) $request->company_id)) {
            return 'als „manuell prüfen" markiert';
        }

        if ($this->hasActiveSubscription((int) $request->tenant_id, (int) $request->company_id)) {
            return 'laufender Premium-Vertrag';
        }

        try {
            $imPortal = $request->runInTenantContext(function () use ($request): ?string {
                $company = Company::withTrashed()->find($request->company_id);

                if ($company === null) {
                    return null;
                }

                if ((bool) $company->is_premium) {
                    return 'Profil ist als Premium geführt';
                }

                $offen = CompanyLead::query()
                    ->where('company_id', $company->getKey())
                    ->whereIn('status', [CompanyLeadStatus::NEW->value, CompanyLeadStatus::CONTACTED->value])
                    ->count();

                return $offen > 0 ? "{$offen} offene Lead-Anfrage(n)" : null;
            });
        } catch (Throwable $exception) {
            Log::warning('profile-requests: Vertragsprüfung fehlgeschlagen', [
                'request_id' => $request->getKey(),
                'exception' => $exception->getMessage(),
            ]);

            return 'Vertragslage nicht prüfbar';
        }

        return is_string($imPortal) ? $imPortal : null;
    }

    /**
     * „Manuell pruefen": Eintrag `tenant_id:company_id` oder — fuer ein
     * ganzes Portal — nur `tenant_id`.
     */
    public function isMarkedForManualReview(int $tenantId, int $companyId): bool
    {
        $liste = array_map(
            static fn ($eintrag): string => trim((string) $eintrag),
            (array) config('profile-requests.auto.manual_review', []),
        );

        return in_array((string) $tenantId, $liste, true)
            || in_array("{$tenantId}:{$companyId}", $liste, true);
    }

    /**
     * Subscription in `active` oder `past_due`. Beides sind laufende
     * Vertraege — `past_due` ist eine offene Zahlung, keine Kuendigung.
     */
    private function hasActiveSubscription(int $tenantId, int $companyId): bool
    {
        return Subscription::query()
            ->whereIn('id', CompanySubscription::query()
                ->where('tenant_id', $tenantId)
                ->where('company_id', $companyId)
                ->select('subscription_id'))
            ->whereIn('status', [SubscriptionStatus::ACTIVE->value, SubscriptionStatus::PAST_DUE->value])
            ->exists();
    }
}
