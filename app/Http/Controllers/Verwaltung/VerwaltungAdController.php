<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Ads\Support\AdThemeContext;
use App\Ads\Support\ThemeAnchorIndex;
use App\Constants\TenancyPermissionConstants;
use App\Models\Portal\AdSetting;
use App\Models\Portal\AdSlot;
use App\Services\TenantPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Werbung im Portal-Backoffice (Werbesystem der ersten Generation).
 *
 * Die Positionen aus `config/ad-positions.php` gelten global, ihre Anker
 * stehen aber je Theme — eine Position ohne Anker im aktiven Theme wird nie
 * ausgeliefert. Darum bietet das Formular nur noch Positionen mit Anker an
 * und die Uebersicht weist bestehende, stumme Slots aus (Ticket #14). Die
 * Pruefregel bleibt absichtlich auf dem ganzen Katalog: ein Altbestand soll
 * speicherbar bleiben, auch wenn sein Anker im Theme fehlt.
 */
class VerwaltungAdController extends VerwaltungBaseController
{
    public function __construct(TenantPermissionService $permissionService)
    {
        parent::__construct($permissionService);
    }

    public function index()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_ADS);

        $this->setBreadcrumbs([
            ['label' => 'Werbung'],
        ]);

        $navigationItems = $this->getNavigationItems();

        $slots = AdSlot::sorted()->get()->groupBy('position');
        $positions = config('ad-positions', []);
        $adSettings = AdSetting::instance();
        $silentPositions = ThemeAnchorIndex::silentPositions(AdThemeContext::current());

        return view('pages.verwaltung.ads.index', compact(
            'navigationItems',
            'slots',
            'positions',
            'adSettings',
            'silentPositions',
        ));
    }

    public function create()
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_ADS);

        $this->setBreadcrumbs([
            ['label' => 'Werbung', 'url' => route('verwaltung.ads.index')],
            ['label' => 'Neuer Ad-Slot'],
        ]);

        $navigationItems = $this->getNavigationItems();
        $positions = $this->selectablePositions();

        return view('pages.verwaltung.ads.create', compact('navigationItems', 'positions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_ADS);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'required|string|in:'.implode(',', array_keys(config('ad-positions', []))),
            'code' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'device_visibility' => 'array',
            'device_visibility.*' => 'in:desktop,tablet,mobile',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['device_visibility'] = $validated['device_visibility'] ?? ['desktop', 'tablet', 'mobile'];

        AdSlot::create($validated);

        return redirect()->route('verwaltung.ads.index')
            ->with('success', 'Ad-Slot wurde erstellt.');
    }

    public function edit(string $id)
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_ADS);

        $adSlot = AdSlot::findOrFail($id);

        $this->setBreadcrumbs([
            ['label' => 'Werbung', 'url' => route('verwaltung.ads.index')],
            ['label' => $adSlot->name],
        ]);

        $navigationItems = $this->getNavigationItems();
        $positions = $this->selectablePositions((string) $adSlot->getAttribute('position'));

        return view('pages.verwaltung.ads.edit', compact('navigationItems', 'adSlot', 'positions'));
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_ADS);

        $adSlot = AdSlot::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'position' => 'required|string|in:'.implode(',', array_keys(config('ad-positions', []))),
            'code' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'device_visibility' => 'array',
            'device_visibility.*' => 'in:desktop,tablet,mobile',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['device_visibility'] = $validated['device_visibility'] ?? ['desktop', 'tablet', 'mobile'];

        $adSlot->update($validated);

        return redirect()->route('verwaltung.ads.index')
            ->with('success', 'Ad-Slot wurde aktualisiert.');
    }

    /**
     * Positionen fuer das Formular: die Anker des aktiven Themes, dazu die
     * bereits gespeicherte Position — sonst wuerde das Bearbeiten eines alten,
     * stummen Slots dessen Position stillschweigend umstellen. Beschriftung
     * der Nachzuegler mit Hinweis, damit der Zustand sichtbar bleibt.
     *
     * @return array<string, string>
     */
    private function selectablePositions(?string $keep = null): array
    {
        $theme = AdThemeContext::current();
        $positions = ThemeAnchorIndex::availablePositions($theme);

        if ($keep !== null && ! array_key_exists($keep, $positions)) {
            $label = config("ad-positions.{$keep}", $keep);
            $positions[$keep] = "{$label} — wird in diesem Portal nicht ausgeliefert";
        }

        return $positions;
    }

    public function updateAdsTxt(Request $request): RedirectResponse
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_ADS);

        $validated = $request->validate([
            'ads_txt_content' => 'nullable|string|max:10000',
        ]);

        $adSettings = AdSetting::instance();
        $adSettings->update($validated);

        return redirect()->route('verwaltung.ads.index')
            ->with('success', 'ads.txt wurde aktualisiert.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $this->requirePermission(TenancyPermissionConstants::PERMISSION_MANAGE_ADS);

        $adSlot = AdSlot::findOrFail($id);
        $name = $adSlot->name;
        $adSlot->delete();

        return redirect()->route('verwaltung.ads.index')
            ->with('success', "Ad-Slot \"{$name}\" wurde gelöscht.");
    }
}
