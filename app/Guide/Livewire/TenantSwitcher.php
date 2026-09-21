<?php

declare(strict_types=1);

namespace App\Guide\Livewire;

use App\Guide\Services\ContentTenantContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Globaler Portal-Umschalter in der Kopfzeile des Content-Panels (#4).
 *
 * Die Auswahl liegt in der Session und gilt fuer alle Seiten des Panels;
 * InitializeContentTenant uebersetzt sie pro Anfrage in tenancy()->initialize().
 * Nach dem Umschalten wird die aktuelle Seite neu geladen — jede Kennzahl und
 * jede Tabelle darauf haengt am Portal, ein Teil-Update waere irrefuehrend.
 */
class TenantSwitcher extends Component
{
    public const ALL_PORTALS = 'all';

    public string $selected = self::ALL_PORTALS;

    public function mount(): void
    {
        $id = $this->context()->selectedId();

        $this->selected = $id === null ? self::ALL_PORTALS : (string) $id;
    }

    public function updatedSelected(string $value): void
    {
        $this->context()->select($value === self::ALL_PORTALS ? null : (int) $value);

        $this->redirect($this->returnUrl());
    }

    public function getTenantsProperty(): TenantCollection
    {
        return $this->context()->available();
    }

    public function render(): View
    {
        return view('content.layout.header', [
            'tenants' => $this->getTenantsProperty(),
        ]);
    }

    private function returnUrl(): string
    {
        $referer = request()->header('Referer');

        // Mit Schraegstrich vergleichen, sonst passte auch https://host.evil.com.
        if (is_string($referer) && ($referer === url('/') || str_starts_with($referer, url('/').'/'))) {
            return $referer;
        }

        return url(config('content.panel.path', 'content'));
    }

    private function context(): ContentTenantContext
    {
        return app(ContentTenantContext::class);
    }
}
