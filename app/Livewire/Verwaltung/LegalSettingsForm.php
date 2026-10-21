<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenantConfigConstants;
use App\Enums\LegalTextKey;
use App\Services\LegalTextService;
use App\Services\TenantBrandingService;
use Livewire\Component;

class LegalSettingsForm extends Component
{
    /**
     * Textarten, die zentral fuer alle Portale gepflegt werden (Ticket #17).
     * Sie sind hier nur noch zu lesen; der portaleigene Text wird nicht mehr
     * ausgeliefert und beim Speichern auch nicht mehr veraendert.
     *
     * Bewusst je Art und nicht pauschal: Solange eine Art zentral leer ist,
     * gilt weiter der Text dieses Portals — dann muss er auch bearbeitbar
     * bleiben. Ein gesperrtes Feld, dessen Inhalt noch wirkt, waere die
     * schlimmere Falle.
     *
     * @var array<string, bool>
     */
    public array $zentralGefuehrt = [];

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
        $this->zentralGefuehrt = $this->zentralGefuehrteArten();
    }

    /**
     * @return array<string, bool>
     */
    private function zentralGefuehrteArten(): array
    {
        $zentral = app(LegalTextService::class);
        $stand = [];

        foreach (LegalTextKey::all() as $key) {
            $stand[$key->value] = $zentral->get($key) !== null;
        }

        return $stand;
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
        //
        // Zentral gefuehrte Arten werden uebergangen (#17): Ihr Wert wird hier
        // nicht geschrieben, weil er nicht mehr ausgeliefert wird. Ihn trotzdem
        // zu speichern wuerde dem Portalbetreiber vormachen, seine Aenderung
        // habe eine Wirkung.
        $zentral = $this->zentralGefuehrteArten();

        $zuSchreiben = [
            TenantConfigConstants::RESPONSIBLE_NAME => $this->responsibleName ?: null,
        ];

        if (! ($zentral[LegalTextKey::IMPRESSUM->value] ?? false)) {
            $zuSchreiben[TenantConfigConstants::IMPRESSUM] = $branding->sanitizeLegalHtml($this->impressum);
        }

        if (! ($zentral[LegalTextKey::DATENSCHUTZ->value] ?? false)) {
            $zuSchreiben[TenantConfigConstants::DATENSCHUTZ] = $branding->sanitizeLegalHtml($this->datenschutz);
        }

        if (! ($zentral[LegalTextKey::EDITORIAL_PRINCIPLES->value] ?? false)) {
            $zuSchreiben[TenantConfigConstants::EDITORIAL_PRINCIPLES] = $branding->sanitizeLegalHtml($this->editorialPrinciples);
        }

        $branding->setMany($tenant, $zuSchreiben);

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
