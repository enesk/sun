<?php

namespace Tests\Feature\Livewire\Portal;

use App\Livewire\Portal\CompanySignup;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Models\User;
use App\Themes\ThemeManager;
use App\Themes\ThemeViewFinder;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Feature\FeatureTest;

/**
 * Firmeneintragung (/eintragen, Theme sun-v2) — der Gastweg mit Schritt 2.
 *
 * Grund des Tests (#52): die Komponente validierte `turnstileToken`, ohne die
 * Property zu besitzen. Livewire wirft dabei "No property found for
 * validation" — ein 500 auf allen Portalen, unabhaengig davon, ob der
 * Bot-Schutz eines Portals scharf ist. Abgeschickt wurde dieser Pfad vorher
 * in keinem Test.
 */
class CompanySignupTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        tenancy()->initialize($this->tenant);

        // /eintragen gibt es nur im Theme sun-v2; ohne dessen Views findet
        // Livewire livewire.portal.company-signup nicht.
        $themeManager = app(ThemeManager::class);
        app(ThemeViewFinder::class)->setThemePaths($themeManager->get('sun-v2'));
        $themeManager->activate('sun-v2');

        // Siteverify darf hier nie gerufen werden: ein leeres Token weist die
        // Rule ohne Netz ab (VerificationResult::missingToken). Ein Ausrutscher
        // soll auffallen und nicht still in den Fail-Mode open laufen.
        Http::preventStrayRequests();

        City::create([
            'name' => 'Nuernberg',
            'slug' => 'nuernberg',
            'zipcode' => '90402',
        ]);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    /**
     * Regression #52: Schritt 2 als Gast muss die Turnstile-Pruefung erreichen
     * und bei fehlendem Token eine Feldmeldung setzen — keine Ausnahme. Das
     * ist der Zustand "Hidden-Input leer", also der Weg eines Bots ohne
     * geloestes Widget.
     */
    public function test_guest_submitting_step_two_gets_a_field_error_instead_of_an_exception(): void
    {
        config()->set('turnstile.enabled', true);

        $component = $this->filledStepTwo()->call('next');

        $component->assertHasErrors('turnstileToken');

        $this->assertDatabaseCount('companies', 0);
        $this->assertNull(User::where('email', 'gast@example.com')->first());
    }

    /**
     * Gegenprobe: ohne Bot-Schutz laeuft derselbe Weg durch und legt Konto
     * samt Eintrag an — der Eintrag bleibt bis zur Freigabe inaktiv.
     */
    public function test_guest_submitting_step_two_creates_account_and_listing(): void
    {
        config()->set('turnstile.enabled', false);

        $component = $this->filledStepTwo()->call('next');

        $component->assertHasNoErrors()->assertSet('done', true);

        $company = Company::firstWhere('name', 'Fahrschule Mustermann');

        $this->assertNotNull($company);
        $this->assertFalse((bool) $company->is_active);
        $this->assertNotNull(User::where('email', 'gast@example.com')->first());
    }

    /**
     * Schritt 1 mit gueltigen Feldern, danach die Kontofelder — der Zustand,
     * in dem der Besucher "Eintrag abschicken" drueckt.
     */
    private function filledStepTwo(): \Livewire\Features\SupportTesting\Testable
    {
        $component = Livewire::test(CompanySignup::class);

        // Mindest-Ausfuellzeit der AntiSpam-Schicht (#8): schneller als drei
        // Sekunden gilt als Bot und wird STILL abgewiesen.
        $this->travel(5)->seconds();

        $component
            ->set('firma', 'Fahrschule Mustermann')
            ->set('strasse', 'Hauptstrasse 1')
            ->set('plz', '90402')
            ->set('ort', 'Nuernberg')
            ->set('tel', '0911 123456')
            ->call('next')
            ->assertSet('step', 2);

        return $component
            ->set('name', 'Max Gast')
            ->set('email', 'gast@example.com')
            ->set('password', 'geheim1234')
            ->set('agb', true);
    }
}
