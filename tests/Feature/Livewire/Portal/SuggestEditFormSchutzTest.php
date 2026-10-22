<?php

namespace Tests\Feature\Livewire\Portal;

use App\Livewire\Portal\SuggestEditForm;
use App\Mail\EditSuggestionNotification;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyEditSuggestion;
use App\Models\Tenant;
use App\ProfileRequests\Models\ProfileRequest;
use App\Themes\ThemeManager;
use App\Themes\ThemeViewFinder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Abnahme #21: die Schutzschicht nach dem Umbau von „Änderung vorschlagen"
 * (#20) — und dass das Altsystem nicht mehr beschrieben wird.
 *
 * Ergänzt SuggestEditFormTest, der die Angaben und die Inbox prüft. Hier
 * stehen die drei Punkte, die der Umbau schwächen könnte, ohne dass es
 * auffiele: Honigtopf, Rate-Limit und der doppelte Schreibweg.
 */
class SuggestEditFormSchutzTest extends FeatureTest
{
    private Tenant $tenant;

    private Company $company;

    private int $anfragenVorher = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        tenancy()->initialize($this->tenant);

        // Das Formular steht nur im Theme sun-v2 (eingebunden in
        // pages/companies/suggest-edit.blade.php dieses Themes).
        $themeManager = app(ThemeManager::class);
        app(ThemeViewFinder::class)->setThemePaths($themeManager->get('sun-v2'));
        $themeManager->activate('sun-v2');

        // Kein Netz: ein Ausrutscher gegen Cloudflare soll auffallen.
        Http::preventStrayRequests();
        config()->set('turnstile.enabled', false);

        $city = City::create(['name' => 'Fuerth', 'slug' => 'fuerth', 'zipcode' => '90762']);

        $this->company = Company::create([
            'name' => 'Pruef Sanitaer',
            'slug' => 'pruef-sanitaer',
            'city_id' => $city->id,
            'street' => 'Ringstrasse',
            'house_no' => '7',
            'zipcode' => '90762',
            'tel' => '0911 222222',
            'website' => 'https://pruef.example',
            'description' => 'Bestandstext.',
            'is_active' => true,
        ]);

        $this->anfragenVorher = ProfileRequest::query()->count();
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    private function neueAnfragen(): int
    {
        return ProfileRequest::query()->count() - $this->anfragenVorher;
    }

    /** Gefüllter Honigtopf: dieselbe Erfolgsmeldung, aber kein Vorgang. */
    public function test_gefuellter_honigtopf_wird_still_abgewiesen(): void
    {
        $this->travel(5)->seconds();

        Livewire::test(SuggestEditForm::class, ['company' => $this->company])
            ->set('antispamHoneypot', 'http://spam.example')
            ->set('kategorien', ['phone'])
            ->set('tel', '0911 999999')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submitted', true);

        $this->assertSame(0, $this->neueAnfragen(), 'Ein Honigtopf-Treffer darf keine Anfrage anlegen.');
    }

    /**
     * Rate-Limit `suggest_edit` — Grenze aus config/antispam.php, gezählt je
     * IP-Hash und Portal. Der Versuch nach der Grenze bekommt eine Meldung am
     * Feld und legt nichts an; ein 429 würde den Besucher vor ein totes
     * Formular stellen.
     */
    public function test_nach_der_grenze_kommt_eine_wartemeldung_und_kein_vorgang(): void
    {
        $grenze = (int) config('antispam.limits.suggest_edit.ip.max');

        $this->assertGreaterThan(0, $grenze, 'Ohne Grenze gibt es kein Rate-Limit zu prüfen.');

        for ($i = 1; $i <= $grenze; $i++) {
            $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);
            $this->travel(5)->seconds();

            $komponente
                ->set('kategorien', ['phone'])
                ->set('tel', '0911 50000'.$i)
                ->call('submit')
                ->assertHasNoErrors();
        }

        $this->assertSame($grenze, $this->neueAnfragen());

        // Der Versuch über der Grenze.
        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);
        $this->travel(5)->seconds();

        $komponente
            ->set('kategorien', ['phone'])
            ->set('tel', '0911 777777')
            ->call('submit')
            ->assertHasErrors('kategorien')
            ->assertSet('submitted', false);

        $this->assertSame($grenze, $this->neueAnfragen(), 'Über der Grenze darf keine weitere Anfrage entstehen.');
    }

    /**
     * Ein Vorgang, ein Ort: das Altsystem `company_edit_suggestions` wird
     * nicht mehr beschrieben und die Mail an das Sammelpostfach entfällt.
     * Stünde der Vorgang an beiden Stellen, wäre die Arbeit doppelt — genau
     * das sollte der Umbau beenden.
     */
    public function test_das_altsystem_wird_nicht_mehr_beschrieben(): void
    {
        Mail::fake();

        $vorschlaegeVorher = CompanyEditSuggestion::query()->count();

        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);
        $this->travel(5)->seconds();

        $komponente
            ->set('kategorien', ['phone', 'website'])
            ->set('tel', '0911 123456')
            ->set('website', 'https://neu.example')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->neueAnfragen());
        $this->assertSame(
            $vorschlaegeVorher,
            CompanyEditSuggestion::query()->count(),
            'Der alte Vorschlag darf nicht mehr mitgeschrieben werden.',
        );

        Mail::assertNotQueued(EditSuggestionNotification::class);
        Mail::assertNotSent(EditSuggestionNotification::class);
    }

    /**
     * Zwei Kategorien, zwei Zeilen — und die alte Freitext-Ablage ist weg:
     * der Diff trägt die Feldnamen des Datenmodells, nicht `phone`/`website`
     * aus der Oberfläche.
     */
    public function test_die_zeilen_tragen_die_feldnamen_des_datenmodells(): void
    {
        $komponente = Livewire::test(SuggestEditForm::class, ['company' => $this->company]);
        $this->travel(5)->seconds();

        $komponente
            ->set('kategorien', ['phone', 'website'])
            ->set('tel', '0911 654321')
            ->set('website', 'https://anders.example')
            ->call('submit')
            ->assertHasNoErrors();

        $anfrage = ProfileRequest::query()->latest('id')->firstOrFail();
        $felder = $anfrage->changes()->pluck('new_value', 'field')->all();

        $this->assertSame(['tel' => '0911 654321', 'website' => 'https://anders.example'], $felder);
    }
}
