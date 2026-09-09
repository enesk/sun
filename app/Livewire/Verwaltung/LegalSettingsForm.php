<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenantConfigConstants;
use App\Services\TenantBrandingService;
use Livewire\Component;

class LegalSettingsForm extends Component
{
    public string $impressum = '';

    public string $datenschutz = '';

    public string $editorialPrinciples = '';

    public string $responsibleName = '';

    // UI State
    public bool $saved = false;

    /**
     * Zustand der Anschrift (#60) — steuert Warnkasten und Wiedergabe.
     *
     * @var array{hasAddress: bool, placeholdersUsed: bool, needsAttention: bool, formatted: ?string}
     */
    public array $addressStatus = [
        'hasAddress' => false,
        'placeholdersUsed' => false,
        'needsAttention' => false,
        'formatted' => null,
    ];

    public function mount(): void
    {
        $tenant = tenant();
        $branding = app(TenantBrandingService::class);

        $this->impressum = $branding->get($tenant, TenantConfigConstants::IMPRESSUM) ?? '';
        $this->datenschutz = $branding->get($tenant, TenantConfigConstants::DATENSCHUTZ) ?? '';
        $this->editorialPrinciples = $branding->get($tenant, TenantConfigConstants::EDITORIAL_PRINCIPLES) ?? '';
        $this->responsibleName = $branding->get($tenant, TenantConfigConstants::RESPONSIBLE_NAME) ?? '';

        $this->addressStatus = $branding->legalAddressStatus($tenant);
    }

    protected function rules(): array
    {
        return [
            'impressum' => ['nullable', 'string', 'max:50000'],
            'datenschutz' => ['nullable', 'string', 'max:50000'],
            'editorialPrinciples' => ['nullable', 'string', 'max:50000'],
            'responsibleName' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $tenant = tenant();
        $branding = app(TenantBrandingService::class);

        // HTML sanitizen — XSS-Schutz weil Templates {!! !!} nutzen.
        // sanitizeLegalHtml() haelt dabei die Platzhalter unversehrt (#50).
        $branding->setMany($tenant, [
            TenantConfigConstants::IMPRESSUM => $branding->sanitizeLegalHtml($this->impressum),
            TenantConfigConstants::DATENSCHUTZ => $branding->sanitizeLegalHtml($this->datenschutz),
            TenantConfigConstants::EDITORIAL_PRINCIPLES => $branding->sanitizeLegalHtml($this->editorialPrinciples),
            TenantConfigConstants::RESPONSIBLE_NAME => $this->responsibleName ?: null,
        ]);

        // Neu bewerten, nachdem die Texte geschrieben sind: ein neu eingefuegter
        // Adressplatzhalter zeigt die Warnung sofort, ein entfernter nimmt sie
        // sofort weg — ohne Neuladen der Seite (#61, §4.1).
        $this->addressStatus = $branding->legalAddressStatus($tenant->refresh());

        $this->saved = true;
        $this->dispatch('toast', type: 'success', message: 'Rechtliche Seiten gespeichert');
    }

    public function render()
    {
        return view('livewire.verwaltung.legal-settings-form');
    }
}
