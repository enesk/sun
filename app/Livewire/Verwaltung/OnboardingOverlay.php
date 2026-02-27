<?php

namespace App\Livewire\Verwaltung;

use App\Models\Portal\Company;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OnboardingOverlay extends Component
{
    public bool $showOverlay = false;
    public string $companyName = '';
    public ?int $companyId = null;
    public int $profileProgress = 0;
    public array $quickActions = [
        'logo' => false,
        'hours' => false,
        'description' => false,
    ];

    public function mount(): void
    {
        $user = Auth::user();

        if (!$user || !$user->shouldShowOnboarding()) {
            return;
        }

        // Lade die zuletzt geclaimte Firma
        $companyId = session('claimed_company_id');
        $company = $companyId
            ? Company::with('openingHours', 'media')->find($companyId)
            : Company::with('openingHours', 'media')->where('user_id', $user->id)->latest()->first();

        if (!$company) {
            return;
        }

        $this->showOverlay = true;
        $this->companyName = session('claimed_company_name', $company->name);
        $this->companyId = $company->id;
        $this->calculateProgress($company);
    }

    public function dismiss(): void
    {
        $user = Auth::user();

        if ($user) {
            $user->dismissOnboarding();
        }

        // Session-Kontext aufräumen
        session()->forget(['claimed_company_id', 'claimed_company_name']);

        $this->showOverlay = false;
    }

    private function calculateProgress(Company $company): void
    {
        $total = 5;
        $filled = 0;

        // Name ist immer da (aus Import)
        $filled++;

        // Logo
        $hasLogo = $company->hasMedia('logo');
        $this->quickActions['logo'] = $hasLogo;
        if ($hasLogo) $filled++;

        // Öffnungszeiten
        $hasHours = $company->relationLoaded('openingHours')
            ? $company->openingHours->isNotEmpty()
            : $company->openingHours()->exists();
        $this->quickActions['hours'] = $hasHours;
        if ($hasHours) $filled++;

        // Beschreibung
        $hasDescription = strlen($company->description ?? '') >= 20;
        $this->quickActions['description'] = $hasDescription;
        if ($hasDescription) $filled++;

        // Adresse
        if ($company->street && $company->zip) $filled++;

        $this->profileProgress = (int) round(($filled / $total) * 100);
    }

    public function render()
    {
        return view('livewire.verwaltung.onboarding-overlay');
    }
}
