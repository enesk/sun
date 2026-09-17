<?php

declare(strict_types=1);

namespace App\Services\Premium;

use App\Dto\Leads\LeadRequest;
use App\Enums\LeadRoute;
use App\Enums\PremiumFeature;
use App\Exceptions\LeadQuotaExceededException;
use App\Mail\Company\NewExclusiveLeadMail;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyLead;
use App\Models\Portal\LeadQuotaUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Entscheidet, wohin eine Anfrage aus dem Profil-Dialog geht (#9, SUN-PREM-009).
 *
 * Exclusive nur mit Feature exclusive_leads UND freiem Monatskontingent
 * (LeadQuotaService) und einer Adresse, an die die Anfrage gehen kann.
 * Sonst Marketplace, also der bisherige Weg ueber widileads.
 *
 * Geprueft wird zweimal: beim Oeffnen des Dialogs (ohne LeadRequest) und beim
 * Absenden in deliverExclusive(). Dort zaehlt das Kontingent atomar hoch;
 * wer das Rennen um den letzten Platz verliert, faellt still auf
 * Marketplace zurueck.
 */
class LeadRoutingService
{
    public function __construct(
        private readonly CompanyEntitlementService $entitlements,
        private readonly LeadQuotaService $quota,
    ) {}

    public function resolve(Company $company, ?LeadRequest $request = null): LeadRoute
    {
        if ($request !== null && ! $request->hasContact()) {
            return LeadRoute::Marketplace;
        }

        // Reihenfolge wichtig: das Kontingent legt beim ersten Zugriff einen Monatseintrag an
        if (! $this->entitlements->can($company, PremiumFeature::ExclusiveLeads)
            || $this->recipient($company) === null
            || ! $this->quota->hasRemaining($company)) {
            return LeadRoute::Marketplace;
        }

        return LeadRoute::Exclusive;
    }

    /**
     * Speichert die Anfrage exklusiv am Betrieb, zaehlt das Kontingent und
     * benachrichtigt den Betrieb. Null heisst: nicht exklusiv, der Dialog
     * uebergibt die Anfrage an widileads.
     *
     * @param  string  $dashboardUrl  Link auf die Anfragen im Betriebsbereich (Domain des Portals)
     */
    public function deliverExclusive(Company $company, LeadRequest $request, string $dashboardUrl): ?CompanyLead
    {
        if ($this->resolve($company, $request) !== LeadRoute::Exclusive) {
            return null;
        }

        try {
            $lead = DB::connection((new CompanyLead)->getConnectionName())->transaction(function () use ($company, $request): CompanyLead {
                $this->quota->consume($company);

                return CompanyLead::create([
                    'company_id' => $company->getKey(),
                    'answers' => $request->answers,
                    'contact_name' => $request->contactName,
                    'contact_email' => $request->contactEmail,
                    'contact_phone' => $request->contactPhone,
                    'funnel_version' => $request->funnelVersion,
                    'quota_period' => LeadQuotaUsage::periodFor(),
                ]);
            });
        } catch (LeadQuotaExceededException) {
            return null;
        }

        Mail::to($this->recipient($company))->queue(new NewExclusiveLeadMail($lead, $company->name, $dashboardUrl));

        return $lead;
    }

    /**
     * Inhaber-Adresse, sonst die E-Mail des Betriebs.
     */
    private function recipient(Company $company): ?string
    {
        /** @var \App\Models\User|null $owner */
        $owner = $company->owner;
        $email = $owner?->email ?: $company->email;

        return is_string($email) && $email !== '' ? $email : null;
    }
}
