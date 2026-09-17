<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company\Dashboard;

use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\Portal\Company;
use App\Services\Premium\ReviewInviteService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Bewertungs-Tools im Betriebsbereich (#12): QR-Code zum Bewertungslink und
 * Einbettungscode mit Vorschau fuer das Widget (Feature review_widget).
 * Eingebettet in /firmenprofil/bewertungen.
 */
class Reviews extends Component
{
    use HasEntitlements;

    #[Locked]
    public int $companyId = 0;

    private ?Company $company = null;

    public function mount(): void
    {
        $this->companyId = (int) $this->company()->getKey();
    }

    public function render(ReviewInviteService $invites): View
    {
        $company = $this->company();
        $widgetAllowed = $this->can(PremiumFeature::ReviewWidget);

        return view('livewire.portal.company.dashboard.reviews', [
            'company' => $company,
            'qrSvg' => $invites->qrSvg($company),
            'widgetAllowed' => $widgetAllowed,
            'embedCode' => $widgetAllowed ? $invites->embedCode($company) : null,
            'widgetUrl' => $widgetAllowed ? $invites->widgetUrl($company) : null,
        ]);
    }

    protected function entitlementCompany(): ?Company
    {
        return $this->company();
    }

    private function company(): Company
    {
        return $this->company ??= Company::ownedBy((int) Auth::id())
            ->when($this->companyId > 0, fn ($query) => $query->whereKey($this->companyId))
            ->firstOrFail();
    }
}
