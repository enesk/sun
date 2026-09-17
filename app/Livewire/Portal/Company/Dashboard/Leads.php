<?php

namespace App\Livewire\Portal\Company\Dashboard;

use App\Constants\CompanyLeadStatus;
use App\Enums\PlanTier;
use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyLead;
use App\Services\Premium\CompanyEntitlementService;
use App\Services\Premium\LeadQuotaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Exklusive Anfragen im Betriebsbereich (#9): Liste mit Statusfilter,
 * Detailansicht mit vollstaendigen Kontaktdaten, Status manuell setzbar.
 * Eingebunden oben auf /firmenprofil/anfragen (pages.dashboard.inquiries.index).
 *
 * Ist das Kontingent erschoepft, steht ein Hinweis samt Upsell ueber der
 * Liste (SUN-PREM-016); neue Anfragen gehen dann ueber den Marktplatz.
 * Nach einem Downgrade bleiben bestehende Anfragen sichtbar.
 *
 * @property-read Company $company
 */
class Leads extends Component
{
    use HasEntitlements;

    private const PAGE_SIZE = 20;

    #[Url(as: 'exklusiv', except: '')]
    public string $status = '';

    #[Url(as: 'anfrage')]
    public ?int $selected = null;

    public int $limit = self::PAGE_SIZE;

    #[Computed]
    public function company(): Company
    {
        return Company::ownedBy((int) Auth::id())->firstOrFail();
    }

    protected function entitlementCompany(): ?Company
    {
        return $this->company;
    }

    public function filter(string $status): void
    {
        $this->status = CompanyLeadStatus::tryFrom($status)?->value ?? '';
        $this->limit = self::PAGE_SIZE;
    }

    public function select(int $leadId): void
    {
        $lead = $this->findLead($leadId);
        $lead->markRead();
        $this->selected = $lead->id;
    }

    public function closeDetail(): void
    {
        $this->selected = null;
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE_SIZE;
    }

    public function setStatus(int $leadId, string $status): void
    {
        validator(['status' => $status], [
            'status' => ['required', Rule::enum(CompanyLeadStatus::class)],
        ])->validate();

        $lead = $this->findLead($leadId);

        $lead->forceFill([
            'status' => $status,
            'status_changed_at' => now(),
            'read_at' => $lead->read_at ?? now(),
        ])->save();
    }

    public function render(): View
    {
        $company = $this->company;
        $filter = CompanyLeadStatus::tryFrom($this->status);
        $canExclusive = $this->can(PremiumFeature::ExclusiveLeads);

        $counts = $this->leads()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        $leads = $this->leads()
            ->when($filter, fn ($query) => $query->withStatus($filter))
            ->latest()
            ->latest('id')
            ->limit($this->limit + 1)
            ->get();

        $selectedLead = $this->selected !== null
            ? $this->leads()->find($this->selected)
            : null;
        $selectedLead?->markRead();

        return view('livewire.portal.company.dashboard.leads', [
            'company' => $company,
            'filter' => $filter,
            'counts' => $counts,
            'total' => array_sum($counts),
            'leads' => $leads->take($this->limit),
            'hasMore' => $leads->count() > $this->limit,
            'selectedLead' => $selectedLead,
            'canExclusive' => $canExclusive,
            'quota' => $canExclusive ? app(LeadQuotaService::class)->summary($company) : null,
            'canUpgrade' => ! app(CompanyEntitlementService::class)->effectiveTier($company)->isAtLeast(PlanTier::Premium),
        ]);
    }

    /**
     * @return Builder<CompanyLead>
     */
    private function leads(): Builder
    {
        return CompanyLead::query()->where('company_id', $this->company->id);
    }

    private function findLead(int $leadId): CompanyLead
    {
        return $this->leads()->findOrFail($leadId);
    }

    /**
     * Kurztext der Anfrage fuer die Liste: erste Antwort mit Inhalt.
     */
    public static function subject(CompanyLead $lead): ?string
    {
        foreach ((array) $lead->answers as $answer) {
            $value = $answer['value_label'] ?? $answer['value'] ?? null;
            $value = is_array($value) ? implode(', ', array_map('strval', $value)) : trim((string) $value);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }
}
