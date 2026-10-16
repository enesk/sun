<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Page;

use App\Ads\EditorToken;
use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;
use App\Ads\Support\AdsAdminPortal;
use App\Filament\Admin\Pages\AdPlacementEditor;
use App\Models\Tenant;
use App\Themes\ThemeManager;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Werbeplätze — Editor (Ticket #7).
 *
 * Die Vorschau selbst (iframe, Rahmen im Portal) gehört zu Ticket #6; hier
 * steht die Panel-Seite: Zustand, Ankerliste je Theme und Gerät, die signierte
 * Adresse und der Gleichlauf zwischen Klick in der Liste und Klick im iframe.
 */
class AdPlacementEditorTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->createAdminUser());
        AdsAdminPortal::select(null);
    }

    public function test_seite_laedt(): void
    {
        $this->sunV2Tenant();

        $this->get(AdPlacementEditor::getUrl([], true, 'admin'))->assertSuccessful();
    }

    public function test_vorgaben_sind_portal_aus_der_sitzung_firmensuche_und_mobil(): void
    {
        $tenant = $this->sunV2Tenant();

        AdsAdminPortal::select((int) $tenant->getKey());

        $seite = Livewire::test(AdPlacementEditor::class);

        $seite->assertSet('data.page_type', AdPageType::SEARCH->value)
            ->assertSet('data.device', AdDevice::MOBILE->value);

        $this->assertSame(390, $seite->instance()->previewWidth());
        $this->assertNotNull($seite->get('data.tenant_id'));
        // Die Firmensuche gibt es in jedem Portal, der Pfad kommt aus der Route.
        $this->assertSame(route('portal.companies.index', [], false), $seite->get('data.path'));
        $this->assertSame($tenant->getKey(), (int) $seite->get('data.tenant_id'));
    }

    /**
     * Die Portalauswahl ist mit Einstellungen › Werbung geteilt (#10): wer
     * hier ein Portal waehlt, findet es dort wieder und umgekehrt.
     */
    public function test_portalwahl_landet_in_der_geteilten_sitzung(): void
    {
        $this->sunV2Tenant();
        $zweites = $this->sunV2Tenant();

        Livewire::test(AdPlacementEditor::class)->set('data.tenant_id', $zweites->getKey());

        $this->assertSame((int) $zweites->getKey(), AdsAdminPortal::currentId());
    }

    public function test_ankerliste_haengt_am_theme_des_portals(): void
    {
        $sunV2 = $this->sunV2Tenant();
        $default = $this->createTenant();

        $seite = Livewire::test(AdPlacementEditor::class)->set('data.tenant_id', $sunV2->getKey());
        $this->assertNotSame([], $seite->instance()->slots(), 'Ein sun-v2-Portal muss Anker haben.');

        // 18 der 23 Portale laufen Theme "default" — dort gibt es (noch) keine
        // Anker, und eine waehlbare Position waere stumm (Ticket #14).
        $seite->set('data.tenant_id', $default->getKey());
        $this->assertSame([], $seite->instance()->slots());
        $this->assertStringContainsString('keine Anker', $seite->instance()->themeHinweis());
    }

    public function test_geraetewechsel_aendert_breite_und_erlaubte_anker(): void
    {
        $tenant = $this->sunV2Tenant();

        $seite = Livewire::test(AdPlacementEditor::class)
            ->set('data.tenant_id', $tenant->getKey())
            ->set('data.page_type', AdPageType::COMPANY->value);

        $mobil = collect($seite->instance()->slots())->firstWhere('devices', 'desktop');
        $this->assertNotNull($mobil, 'Das Firmenprofil hat Anker, die nur auf dem Desktop stehen.');
        $this->assertFalse($mobil['allows_device'], 'Ein Desktop-Anker ist in der Mobil-Ansicht nicht erlaubt.');

        $seite->set('data.device', AdDevice::DESKTOP->value);

        $this->assertSame(1280, $seite->instance()->previewWidth());
        $desktop = collect($seite->instance()->slots())->firstWhere('key', $mobil['key']);
        $this->assertTrue($desktop['allows_device']);
    }

    public function test_klick_in_der_liste_und_klick_im_iframe_fuehren_zum_selben_zustand(): void
    {
        $tenant = $this->sunV2Tenant();
        AdsAdminPortal::select((int) $tenant->getKey());

        $seite = Livewire::test(AdPlacementEditor::class);
        $key = $seite->instance()->slots()[0]['key'];

        $seite->call('selectSlot', $key)->assertSet('selectedSlotKey', $key);

        $seite->call('selectSlot', null)->assertSet('selectedSlotKey', null);
        $seite->dispatch('ad-editor-slot-clicked', key: $key)->assertSet('selectedSlotKey', $key);
    }

    public function test_unbekannter_anker_wird_nicht_ausgewaehlt(): void
    {
        $tenant = $this->sunV2Tenant();
        AdsAdminPortal::select((int) $tenant->getKey());

        Livewire::test(AdPlacementEditor::class)
            ->call('selectSlot', 'gibt_es_nicht')
            ->assertSet('selectedSlotKey', null);
    }

    public function test_gemeldete_anker_decken_fehlende_auf(): void
    {
        $tenant = $this->sunV2Tenant();
        AdsAdminPortal::select((int) $tenant->getKey());

        $seite = Livewire::test(AdPlacementEditor::class);
        $keys = array_column($seite->instance()->slots(), 'key');

        // Das Portal meldet alle ausser dem letzten: der steht im Katalog,
        // aber nicht in der View.
        $seite->dispatch('ad-editor-slots-ready', keys: array_slice($keys, 0, -1));

        $this->assertSame([end($keys)], $seite->instance()->missingSlotKeys());
    }

    public function test_geraetewechsel_raeumt_auswahl_und_gemeldete_anker_weg(): void
    {
        $tenant = $this->sunV2Tenant();
        AdsAdminPortal::select((int) $tenant->getKey());

        $seite = Livewire::test(AdPlacementEditor::class);
        $keys = array_column($seite->instance()->slots(), 'key');

        $seite->call('selectSlot', $keys[0])
            ->dispatch('ad-editor-slots-ready', keys: $keys)
            ->set('data.device', AdDevice::DESKTOP->value);

        $seite->assertSet('selectedSlotKey', null)->assertSet('reportedSlotKeys', []);
    }

    public function test_ohne_bekannte_herkunft_nennt_die_seite_den_grund(): void
    {
        $tenant = $this->sunV2Tenant();
        AdsAdminPortal::select((int) $tenant->getKey());

        // Ohne ADS_EDITOR_ORIGIN verwirft der Editor im Portal jede Nachricht
        // der Bruecke — dann liesse sich kein Anker anklicken.
        config(['ad-slots.editor_origin' => null]);

        $seite = Livewire::test(AdPlacementEditor::class);

        $this->assertSame('', EditorToken::origin());
        $this->assertStringContainsString('ADS_EDITOR_ORIGIN', (string) $seite->instance()->previewHint());
    }

    public function test_signierte_adresse_steht_auf_der_portal_domain(): void
    {
        $tenant = $this->sunV2Tenant();
        AdsAdminPortal::select((int) $tenant->getKey());

        $seite = Livewire::test(AdPlacementEditor::class);
        $url = $seite->instance()->previewUrl();

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://'.$tenant->domain.'/', $url);
        $this->assertStringContainsString(EditorToken::SIGNATURE_PARAM.'=', $url);
        $this->assertStringContainsString(EditorToken::EXPIRES_PARAM.'=', $url);
        $this->assertStringContainsString(EditorToken::PAGE_TYPE_PARAM.'='.AdPageType::SEARCH->value, $url);
        $this->assertNull($seite->instance()->previewHint());

        // Die Herkunft fuer die Bruecke muss zum Schema der Adresse passen,
        // sonst verwirft sie jede Nachricht.
        $this->assertSame('https://'.$tenant->domain, $seite->instance()->previewOrigin());
    }

    /**
     * Der eigentliche Vertrag zwischen #7 und #6: was das Panel baut, muss die
     * Middleware im Portal annehmen — gebunden an Host und Pfad.
     */
    public function test_portal_nimmt_den_token_des_panels_an(): void
    {
        $tenant = $this->sunV2Tenant();
        AdsAdminPortal::select((int) $tenant->getKey());

        $url = Livewire::test(AdPlacementEditor::class)->instance()->previewUrl();

        $this->assertTrue(EditorToken::valid(Request::create((string) $url)));

        // Derselbe Token auf einem anderen Pfad oeffnet keine zweite Seite.
        $fremd = str_replace(route('portal.companies.index', [], false).'?', '/eine-andere-seite?', (string) $url);
        $this->assertFalse(EditorToken::valid(Request::create($fremd)));

        // Und nicht auf einem anderen Portal.
        $anderesPortal = str_replace((string) $tenant->domain, 'fremdes-portal.test', (string) $url);
        $this->assertFalse(EditorToken::valid(Request::create($anderesPortal)));
    }

    public function test_fehlender_inhalt_sagt_es_statt_eine_adresse_zu_raten(): void
    {
        $tenant = $this->sunV2Tenant();
        AdsAdminPortal::select((int) $tenant->getKey());
        // Ein frisches Portal hat keinen Ratgeber-Artikel.
        $seite = Livewire::test(AdPlacementEditor::class)->set('data.page_type', AdPageType::GUIDE->value);

        $this->assertNull($seite->get('data.path'));
        $this->assertNull($seite->instance()->previewUrl());
        $this->assertStringContainsString('noch keine Seite dieses Typs', (string) $seite->instance()->previewHint());
    }

    public function test_portal_ohne_domain_hat_keine_vorschau(): void
    {
        $tenant = $this->sunV2Tenant();
        AdsAdminPortal::select((int) $tenant->getKey());
        $tenant->domain = null;
        $tenant->save();

        $seite = Livewire::test(AdPlacementEditor::class);

        $this->assertNull($seite->instance()->previewUrl());
        $this->assertNull($seite->instance()->previewOrigin());
        $this->assertStringContainsString('keine Domain', (string) $seite->instance()->previewHint());
    }

    private function sunV2Tenant(): Tenant
    {
        $tenant = $this->createTenant();
        $tenant->domain = 'editor-probe-'.$tenant->getKey().'.test';
        $tenant->save();

        app(ThemeManager::class)->setTenantTheme($tenant->fresh(), 'sun-v2');

        return $tenant->fresh();
    }
}
