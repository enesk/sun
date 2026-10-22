<?php

namespace Tests\Feature\Livewire\Portal;

use App\Livewire\Portal\SuggestEditForm;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\ProfileRequests\Enums\RequestType;
use App\ProfileRequests\Models\ProfileRequest;
use App\Themes\ThemeManager;
use App\Themes\ThemeViewFinder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * „Änderung vorschlagen" nach dem Umbau auf getrennte Angaben (#20).
 *
 * Der Punkt des Tickets ist nicht das Aufklappen, sondern was in der Inbox
 * ankommt: **drei ausgefüllte Kategorien müssen drei Zeilen ergeben**, nicht
 * einen Textblock. Vorher legte das Formular eine `CompanyEditSuggestion` mit
 * einem Freitextfeld an und schickte eine Mail — die Inbox sah davon nichts.
 */
class SuggestEditFormTest extends FeatureTest
{
    private Tenant $tenant;

    private Company $company;

    /**
     * Stand der Anfragen beim Testbeginn. Die Central-DB wird zwischen den
     * Tests nicht geleert, deshalb zaehlt nur der Zuwachs.
     */
    private int $anfragenVorher = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        tenancy()->initialize($this->tenant);

        // Das Formular gibt es nur im Theme sun-v2.
        $themeManager = app(ThemeManager::class);
        app(ThemeViewFinder::class)->setThemePaths($themeManager->get('sun-v2'));
        $themeManager->activate('sun-v2');

        // Siteverify darf nie gerufen werden: ein leeres Token weist die Rule
        // ohne Netz ab. Ein Ausrutscher soll auffallen.
        Http::preventStrayRequests();

        $city = City::create(['name' => 'Nuernberg', 'slug' => 'nuernberg', 'zipcode' => '90402']);

        $this->company = Company::create([
            'name' => 'Muster Sanitaer',
            'slug' => 'muster-sanitaer',
            'city_id' => $city->id,
            'street' => 'Hauptstrasse',
            'house_no' => '1',
            'zipcode' => '90402',
            'tel' => '0911 111111',
            'website' => 'https://alt.example',
            'description' => 'Alte Leistungsbeschreibung.',
            'is_active' => true,
        ]);

        $this->anfragenVorher = ProfileRequest::query()->count();
    }

    /** Wie viele Anfragen sind in diesem Test entstanden? */
    private function neueAnfragen(): int
    {
        return ProfileRequest::query()->count() - $this->anfragenVorher;
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    /** Die Felder zeigen den Ist-Stand, damit der Melder nur das Falsche ändert. */
    public function test_die_felder_sind_mit_dem_aktuellen_stand_vorbelegt(): void
    {
        Livewire::test(SuggestEditForm::class, ['company' => $this->company])
            ->assertSet('street', 'Hauptstrasse')
            ->assertSet('houseNo', '1')
            ->assertSet('zipcode', '90402')
            ->assertSet('city', 'Nuernberg')
            ->assertSet('tel', '0911 111111')
            ->assertSet('website', 'https://alt.example')
            ->assertSet('description', 'Alte Leistungsbeschreibung.');
    }

    /**
     * Aufklappen: am Anfang ist kein Feld offen, mit der Kategorie kommt
     * genau ihres dazu, und ein zugeklapptes Feld steht nicht im Markup —
     * sonst koennte es Werte einreichen, die niemand gewaehlt hat.
     */
    public function test_die_felder_klappen_erst_mit_der_kategorie_auf(): void
    {
        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);

        $komponente
            ->assertDontSeeHtml('wire:model="street"')
            ->assertDontSeeHtml('wire:model="tel"')
            ->assertSeeHtml('value="address"');

        $komponente->set('kategorien', ['phone'])
            ->assertSeeHtml('wire:model="tel"')
            ->assertDontSeeHtml('wire:model="street"');

        $komponente->set('kategorien', ['phone', 'address'])
            ->assertSeeHtml('wire:model="tel"')
            ->assertSeeHtml('wire:model="street"')
            ->assertSeeHtml('wire:model="zipcode"');
    }

    /** "Existiert nicht mehr" hat kein Eingabefeld, nur die Markierung. */
    public function test_existiert_nicht_mehr_hat_kein_eingabefeld(): void
    {
        Livewire::test(SuggestEditForm::class, ['company' => $this->company])
            ->set('kategorien', ['closed'])
            ->assertDontSeeHtml('wire:model="note"')
            ->assertDontSeeHtml('wire:model="duplicateOf"')
            ->assertSee(__('portal.suggest_edit.hints.closed'));
    }

    /**
     * Der Kern des Tickets: drei Kategorien, drei geänderte Felder — und in
     * `request_changes` stehen **drei** Zeilen, je mit altem und neuem Wert.
     */
    public function test_drei_kategorien_ergeben_drei_zeilen(): void
    {
        config()->set('turnstile.enabled', false);

        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);

        // Mindest-Ausfuellzeit (#22): der Zeitstempel entsteht beim Mounten,
        // die Uhr muss danach vorgestellt werden — vorher gestellt zaehlt sie
        // als "zu schnell" und die Anfrage wird still abgewiesen.
        $this->travel(5)->seconds();

        $komponente
            ->set('kategorien', ['address', 'phone', 'services'])
            ->set('street', 'Bahnhofstrasse')
            ->set('tel', '0911 222222')
            ->set('description', 'Neue Leistungsbeschreibung.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $request = ProfileRequest::query()->latest('id')->first();

        $this->assertNotNull($request);
        $this->assertSame(RequestType::CHANGE, $request->type);

        $zeilen = $request->changes()->get()->keyBy('field');

        $this->assertCount(3, $zeilen, 'Erwartet sind drei getrennte Zeilen, nicht ein Textblock.');
        $this->assertSame('Bahnhofstrasse', $zeilen['street']->new_value);
        $this->assertSame('Hauptstrasse', $zeilen['street']->old_value);
        $this->assertSame('0911 222222', $zeilen['tel']->new_value);
        $this->assertSame('Neue Leistungsbeschreibung.', $zeilen['description']->new_value);
    }

    /** Unveränderte Felder fallen weg — die Vorbelegung darf keine Zeile werden. */
    public function test_unveraenderte_felder_erzeugen_keine_zeile(): void
    {
        config()->set('turnstile.enabled', false);

        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);

        // Mindest-Ausfuellzeit (#22): der Zeitstempel entsteht beim Mounten,
        // die Uhr muss danach vorgestellt werden — vorher gestellt zaehlt sie
        // als "zu schnell" und die Anfrage wird still abgewiesen.
        $this->travel(5)->seconds();

        $komponente
            ->set('kategorien', ['address'])
            ->set('zipcode', '90403')
            ->call('submit')
            ->assertHasNoErrors();

        $zeilen = ProfileRequest::query()->latest('id')->first()->changes()->get();

        $this->assertCount(1, $zeilen);
        $this->assertSame('zipcode', $zeilen->first()->field);
    }

    /**
     * „Betrieb existiert nicht mehr" ist eine Löschanfrage, keine
     * Änderungszeile — und die Angabe steht als Anmerkung an der Anfrage.
     */
    public function test_existiert_nicht_mehr_wird_eine_loeschanfrage(): void
    {
        config()->set('turnstile.enabled', false);

        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);

        // Mindest-Ausfuellzeit (#22): der Zeitstempel entsteht beim Mounten,
        // die Uhr muss danach vorgestellt werden — vorher gestellt zaehlt sie
        // als "zu schnell" und die Anfrage wird still abgewiesen.
        $this->travel(5)->seconds();

        $komponente
            ->set('kategorien', ['closed'])
            ->call('submit')
            ->assertHasNoErrors();

        $request = ProfileRequest::query()->latest('id')->first();

        $this->assertSame(RequestType::DELETE, $request->type);
        $this->assertCount(0, $request->changes()->get());
        $this->assertStringContainsString('existiert nicht mehr', (string) $request->message);
    }

    /**
     * Die vier Kategorien ohne Zielfeld im Diff landen als Anmerkung, je mit
     * ihrer Beschriftung — sonst wäre in der Inbox nicht erkennbar, worum es
     * geht.
     */
    public function test_kategorien_ohne_diff_feld_gehen_als_anmerkung_mit(): void
    {
        config()->set('turnstile.enabled', false);

        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);

        // Mindest-Ausfuellzeit (#22): der Zeitstempel entsteht beim Mounten,
        // die Uhr muss danach vorgestellt werden — vorher gestellt zaehlt sie
        // als "zu schnell" und die Anfrage wird still abgewiesen.
        $this->travel(5)->seconds();

        $komponente
            ->set('kategorien', ['hours', 'duplicate', 'other'])
            ->set('openingHours', 'Mo-Fr 8-18 Uhr')
            ->set('duplicateOf', 'Muster Sanitaer GmbH, Bahnhofstr. 5')
            ->set('note', 'Der Betrieb ist umgezogen.')
            ->call('submit')
            ->assertHasNoErrors();

        $request = ProfileRequest::query()->latest('id')->first();
        $anmerkung = (string) $request->message;

        $this->assertCount(0, $request->changes()->get());
        $this->assertStringContainsString('Mo-Fr 8-18 Uhr', $anmerkung);
        $this->assertStringContainsString('Bahnhofstr. 5', $anmerkung);
        $this->assertStringContainsString('umgezogen', $anmerkung);
        $this->assertStringContainsString('Öffnungszeiten', $anmerkung);
    }

    /** Ohne Kategorie keine Anfrage. */
    public function test_ohne_kategorie_kommt_eine_feldmeldung(): void
    {
        config()->set('turnstile.enabled', false);

        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);

        // Mindest-Ausfuellzeit (#22): der Zeitstempel entsteht beim Mounten,
        // die Uhr muss danach vorgestellt werden — vorher gestellt zaehlt sie
        // als "zu schnell" und die Anfrage wird still abgewiesen.
        $this->travel(5)->seconds();

        $komponente
            ->call('submit')
            ->assertHasErrors('kategorien');

        $this->assertSame(0, $this->neueAnfragen());
    }

    /**
     * Die Vorbelegung unverändert abzuschicken darf keine leere Anfrage
     * anlegen — sonst steht in der Inbox ein Vorgang ohne eine einzige Zeile.
     */
    public function test_unveraenderte_vorbelegung_legt_keine_anfrage_an(): void
    {
        config()->set('turnstile.enabled', false);

        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);

        // Mindest-Ausfuellzeit (#22): der Zeitstempel entsteht beim Mounten,
        // die Uhr muss danach vorgestellt werden — vorher gestellt zaehlt sie
        // als "zu schnell" und die Anfrage wird still abgewiesen.
        $this->travel(5)->seconds();

        $komponente
            ->set('kategorien', ['address', 'phone'])
            ->call('submit')
            ->assertHasErrors('kategorien');

        $this->assertSame(0, $this->neueAnfragen());
    }

    /**
     * Der Schutz bleibt vollständig: ohne Turnstile-Token keine Anfrage, und
     * zwar als Feldmeldung statt als Ausnahme (#52).
     */
    public function test_ohne_turnstile_token_entsteht_keine_anfrage(): void
    {
        config()->set('turnstile.enabled', true);
        // Die Aktion `contact` ist in der Vorgabe aus (Rollout Portal fuer
        // Portal, #13) — ohne dieses Einschalten prueft die Rule gar nicht.
        config()->set('turnstile.actions.contact.enabled', true);

        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);

        // Mindest-Ausfuellzeit (#22): der Zeitstempel entsteht beim Mounten,
        // die Uhr muss danach vorgestellt werden — vorher gestellt zaehlt sie
        // als "zu schnell" und die Anfrage wird still abgewiesen.
        $this->travel(5)->seconds();

        $komponente
            ->set('kategorien', ['phone'])
            ->set('tel', '0911 333333')
            ->call('submit')
            ->assertHasErrors('turnstileToken');

        $this->assertSame(0, $this->neueAnfragen());
    }

    /** Honigtopf und Mindest-Ausfuellzeit weisen still ab: Erfolg, aber nichts angelegt. */
    public function test_zu_schnell_abgeschickt_wird_still_abgewiesen(): void
    {
        config()->set('turnstile.enabled', false);

        Livewire::test(SuggestEditForm::class, ['company' => $this->company])
            ->set('kategorien', ['phone'])
            ->set('tel', '0911 444444')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(0, $this->neueAnfragen());
    }
}
