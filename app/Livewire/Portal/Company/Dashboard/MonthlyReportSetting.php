<?php

declare(strict_types=1);

namespace App\Livewire\Portal\Company\Dashboard;

use App\Enums\PremiumFeature;
use App\Livewire\Concerns\HasEntitlements;
use App\Models\Portal\Company;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Schalter "Monatsbericht" in /firmenprofil/einstellungen (#16). Gespeichert
 * wird die Abmeldung (companies.monthly_report_opted_out_at); ohne Feature
 * monthly_report ist der Schalter gesperrt.
 */
class MonthlyReportSetting extends Component
{
    use HasEntitlements;

    #[Locked]
    public int $companyId = 0;

    private ?Company $company = null;

    public function mount(): void
    {
        $this->companyId = (int) $this->company()->getKey();
    }

    public function toggle(): void
    {
        if (! $this->can(PremiumFeature::MonthlyReport)) {
            return;
        }

        $company = $this->company();
        $enable = $company->monthly_report_opted_out_at !== null;

        $company->forceFill(['monthly_report_opted_out_at' => $enable ? null : now()])->save();

        $this->dispatch('toast', type: 'success', message: __($enable
            ? 'portal.owner.statistics.report.saved_on'
            : 'portal.owner.statistics.report.saved_off'));
    }

    public function render(): View
    {
        $allowed = $this->can(PremiumFeature::MonthlyReport);

        return view('livewire.portal.company.dashboard.monthly-report-setting', [
            'allowed' => $allowed,
            'enabled' => $allowed && $this->company()->monthly_report_opted_out_at === null,
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
