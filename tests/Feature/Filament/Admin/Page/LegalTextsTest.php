<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Admin\Page;

use App\Constants\TenantConfigConstants;
use App\Enums\LegalTextKey;
use App\Filament\Admin\Pages\LegalTexts;
use App\Models\LegalText;
use App\Services\LegalTextService;
use App\Services\TenantBrandingService;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Einstellungen › Rechtliche Texte (Ticket #17).
 *
 * Der Test deckt vor allem die Falle ab, die am 22.10.2026 auf der Produktion
 * zugeschlagen hat: Ein **leerer String** in einem RichEditor-Feld bringt die
 * ganze Seite zu Fall. Der Editor schickt den Wert durch Tiptap, und
 * `Tiptap\Core\DOMParser::getDocumentBody()` wirft bei '' einen TypeError. Im
 * Browser steht dann nur "There was an error while attempting to load this
 * page" — ohne Hinweis auf die Ursache.
 *
 * Das trifft die Übernahme-Aktion bei fast jedem Portal, weil die
 * redaktionellen Grundsätze dort meist leer sind (es greift der eingebaute
 * Standardtext).
 */
class LegalTextsTest extends FeatureTest
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->createAdminUser());
        LegalText::query()->delete();
    }

    /** Der Editor liefert je Fassung String oder strukturierten Zustand. */
    private function alsText(mixed $zustand): string
    {
        return is_array($zustand) ? json_encode($zustand, JSON_UNESCAPED_UNICODE) : (string) $zustand;
    }

    public function test_seite_laedt(): void
    {
        $this->get(LegalTexts::getUrl([], true, 'admin'))->assertSuccessful();
    }

    public function test_seite_laedt_auch_mit_gepflegten_texten(): void
    {
        $service = app(LegalTextService::class);
        $service->set(LegalTextKey::IMPRESSUM, '<p>Angaben gemäß § 5 DDG</p>');
        $service->set(LegalTextKey::DATENSCHUTZ, '<p>Datenschutz</p>');

        $this->get(LegalTexts::getUrl([], true, 'admin'))->assertSuccessful();

        $seite = Livewire::test(LegalTexts::class);

        $this->assertStringContainsString(
            'Angaben gemäß § 5 DDG',
            $this->alsText($seite->get('data.'.LegalTextKey::IMPRESSUM->value)),
        );
    }

    public function test_uebernahme_aus_einem_portal_mit_teilweise_leeren_texten_bricht_nicht_ab(): void
    {
        $tenant = $this->createTenant();
        $branding = app(TenantBrandingService::class);

        // Genau die Lage auf der Produktion: Impressum und Datenschutz
        // gepflegt, die redaktionellen Grundsätze leer.
        $branding->setMany($tenant, [
            TenantConfigConstants::IMPRESSUM => '<p>Rolland Szalai<br>[BETREIBER_STRASSE]</p>',
            TenantConfigConstants::DATENSCHUTZ => '<p>Datenschutz dieses Portals</p>',
            TenantConfigConstants::EDITORIAL_PRINCIPLES => null,
        ]);

        // Der entscheidende Punkt ist, dass die Seite ueberhaupt antwortet:
        // Mit '' statt null wirft Tiptap einen TypeError und der Browser
        // zeigt nur "There was an error while attempting to load this page".
        $seite = Livewire::test(LegalTexts::class)
            ->set('data.vorlage_tenant_id', (int) $tenant->getKey())
            ->assertHasNoErrors();

        $this->assertStringContainsString(
            '[BETREIBER_STRASSE]',
            $this->alsText($seite->get('data.'.LegalTextKey::IMPRESSUM->value)),
            'Die uebernommene Fassung muss die Platzhalter mitbringen.',
        );
    }

    public function test_uebernahme_aus_einem_portal_ganz_ohne_texte_bricht_nicht_ab(): void
    {
        $tenant = $this->createTenant();

        // Vor dem Fix brach dieser Aufruf mit einem TypeError aus Tiptap ab.
        Livewire::test(LegalTexts::class)
            ->set('data.vorlage_tenant_id', (int) $tenant->getKey())
            ->assertHasNoErrors()
            ->call('save')
            ->assertHasNoErrors();

        // Ein Portal ohne Texte darf die zentrale Fassung nicht als gepflegt
        // hinterlassen — sonst liefern alle Portale leere Rechtstextseiten.
        $service = app(LegalTextService::class);

        foreach (LegalTextKey::all() as $key) {
            $this->assertNull(
                $service->get($key),
                "Leeres Markup darf fuer {$key->value} nicht als gepflegt gelten.",
            );
        }
    }

    public function test_leerraum_im_portaltext_wird_zu_null_und_nicht_uebernommen(): void
    {
        $tenant = $this->createTenant();

        app(TenantBrandingService::class)->setMany($tenant, [
            TenantConfigConstants::IMPRESSUM => '   ',
        ]);

        Livewire::test(LegalTexts::class)
            ->set('data.vorlage_tenant_id', (int) $tenant->getKey())
            ->assertHasNoErrors()
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(app(LegalTextService::class)->get(LegalTextKey::IMPRESSUM));
    }

    public function test_speichern_schreibt_alle_drei_arten(): void
    {
        Livewire::test(LegalTexts::class)
            ->set('data.'.LegalTextKey::IMPRESSUM->value, '<p>Impressum zentral</p>')
            ->set('data.'.LegalTextKey::DATENSCHUTZ->value, '<p>Datenschutz zentral</p>')
            ->call('save')
            ->assertHasNoErrors();

        $service = app(LegalTextService::class);

        $this->assertSame('<p>Impressum zentral</p>', $service->get(LegalTextKey::IMPRESSUM));
        $this->assertSame('<p>Datenschutz zentral</p>', $service->get(LegalTextKey::DATENSCHUTZ));
        // Der Editor schickt fuer das unberuehrte Feld '<p></p>'. Das darf
        // nicht als gepflegt gelten.
        $this->assertNull($service->get(LegalTextKey::EDITORIAL_PRINCIPLES));
    }

    public function test_platzhalter_ueberleben_das_speichern(): void
    {
        $vorlage = '<p>[BETREIBER_STRASSE], [BETREIBER_PLZ] [BETREIBER_ORT]</p>';

        Livewire::test(LegalTexts::class)
            ->set('data.'.LegalTextKey::IMPRESSUM->value, $vorlage)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertStringContainsString('[BETREIBER_STRASSE]', (string) app(LegalTextService::class)->get(LegalTextKey::IMPRESSUM));
    }
}
