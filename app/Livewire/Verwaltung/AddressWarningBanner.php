<?php

namespace App\Livewire\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Services\TenantBrandingService;
use App\Services\TenantPermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Warnbanner „Anschrift fehlt" für die Verwaltungs-Übersicht (Vorgabe #61, §7).
 *
 * Erscheint nur, wenn Impressum oder Datenschutz einen Adressplatzhalter
 * ausgeben und kein Adressdatensatz hinterlegt ist — und nur für Benutzer,
 * die die Einstellungen ändern dürfen.
 *
 * Die gesamte Logik sitzt in render(), die Komponente hält keinen Zustand.
 */
class AddressWarningBanner extends Component
{
    public function render()
    {
        $tenant = tenant();
        $user = Auth::user();

        if (! $tenant || ! $user) {
            return view('livewire.verwaltung.address-warning-banner', ['show' => false]);
        }

        // Recht selbst prüfen: view()->shared('dashboardPermissions') ist beim
        // Livewire-Update-Request nicht verlässlich gesetzt.
        $mayUpdate = $user->isAdmin() || app(TenantPermissionService::class)->tenantUserHasPermissionTo(
            $tenant,
            $user,
            TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS,
        );

        if (! $mayUpdate) {
            return view('livewire.verwaltung.address-warning-banner', ['show' => false]);
        }

        $status = app(TenantBrandingService::class)->legalAddressStatus($tenant);

        return view('livewire.verwaltung.address-warning-banner', [
            'show' => $status['needsAttention'],
        ]);
    }
}
