<?php

namespace App\Http\Controllers\Verwaltung;

use App\Constants\TenancyPermissionConstants;
use App\Constants\TenantConfigConstants;
use App\Services\TenantBrandingService;
use App\Themes\ThemeManager;
use Illuminate\Http\Request;

class VerwaltungSettingsController extends VerwaltungBaseController
{
    /**
     * General settings — Workspace name, address, contact, features.
     */
    public function general()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Einstellungen'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.settings.general', compact('navigationItems'));
    }

    /**
     * Theme settings — Theme selection, branding colors, logo, fonts.
     */
    public function theme()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Einstellungen', 'url' => route('verwaltung.settings.general')],
            ['label' => 'Theme'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.settings.theme', compact('navigationItems'));
    }

    /**
     * Legal settings — Impressum + Datenschutz.
     */
    public function legal()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_UPDATE_TENANT_SETTINGS);

        $this->setBreadcrumbs([
            ['label' => 'Übersicht', 'url' => route('verwaltung.index')],
            ['label' => 'Einstellungen', 'url' => route('verwaltung.settings.general')],
            ['label' => 'Rechtliches'],
        ]);

        $navigationItems = $this->getNavigationItems();

        return view('pages.verwaltung.settings.legal', compact('navigationItems'));
    }
}
