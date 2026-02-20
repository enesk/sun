<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenantConfigConstants;
use App\Services\TenantBrandingService;
use Livewire\Component;

class LegalSettingsForm extends Component
{
    public string $impressum = '';
    public string $datenschutz = '';

    // UI State
    public bool $saved = false;

    public function mount(): void
    {
        $tenant = tenant();
        $branding = app(TenantBrandingService::class);

        $this->impressum = $branding->get($tenant, TenantConfigConstants::IMPRESSUM) ?? '';
        $this->datenschutz = $branding->get($tenant, TenantConfigConstants::DATENSCHUTZ) ?? '';
    }

    protected function rules(): array
    {
        return [
            'impressum' => ['nullable', 'string', 'max:50000'],
            'datenschutz' => ['nullable', 'string', 'max:50000'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $tenant = tenant();
        $branding = app(TenantBrandingService::class);

        // HTML sanitizen — XSS-Schutz weil Templates {!! !!} nutzen
        $allowedHtml = 'p,br,strong,em,u,h1,h2,h3,h4,ul,ol,li,a[href|target|rel],table,thead,tbody,tr,th,td';

        $branding->setMany($tenant, [
            TenantConfigConstants::IMPRESSUM => $this->impressum
                ? clean($this->impressum, ['HTML.Allowed' => $allowedHtml])
                : null,
            TenantConfigConstants::DATENSCHUTZ => $this->datenschutz
                ? clean($this->datenschutz, ['HTML.Allowed' => $allowedHtml])
                : null,
        ]);

        $this->saved = true;
        $this->dispatch('toast', message: 'Rechtliche Seiten gespeichert', type: 'success');
    }

    public function render()
    {
        return view('livewire.verwaltung.legal-settings-form');
    }
}
