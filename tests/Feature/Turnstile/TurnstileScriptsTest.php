<?php

namespace Tests\Feature\Turnstile;

use App\Models\Tenant;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\Feature\FeatureTest;

/**
 * <x-turnstile-scripts /> und die Regel dahinter (#53).
 *
 * Grund des Tests: das Widget der Firmeneintragung erscheint fuer Gaeste erst
 * in Schritt 2. Seine Skripte legt es in den Stack 'scripts', und der wird
 * ausschliesslich beim Rendern des Layouts geleert — in einem Livewire-Umlauf
 * gibt es kein @stack mehr. Erst in Schritt 2 angefordert kamen sie deshalb
 * nie an: das Markup stand da, api.js und resources/js/turnstile.js fehlten,
 * der Hidden-Input blieb leer, und Cloudflare meldete beim Abschicken
 * `missing-input-response` ohne Siteverify-Aufruf (duration_ms NULL).
 */
class TurnstileScriptsTest extends FeatureTest
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        tenancy()->initialize($this->tenant);

        config()->set('turnstile.enabled', true);

        $this->withoutVite();

        // Teilt sonst die ShareErrorsFromSession-Middleware zu; Blade::render
        // laeuft ohne sie und das Widget liest $errors.
        View::share('errors', new ViewErrorBag);
    }

    protected function tearDown(): void
    {
        tenancy()->end();

        parent::tearDown();
    }

    /** Ohne Widget auf der Seite muessen die Skripte trotzdem im Stack landen. */
    public function test_scripts_reach_the_stack_without_a_widget(): void
    {
        $html = Blade::render('<x-turnstile-scripts action="company_listing" />@stack(\'scripts\')');

        $this->assertStringContainsString('challenges.cloudflare.com', $html);
        $this->assertStringContainsString('onload=onTurnstileLoad', $html);
        $this->assertStringNotContainsString('data-turnstile-root', $html);
    }

    /**
     * Mehrfache Einbindung ist unschaedlich: das @once liefert api.js genau
     * einmal je Anfrage — auch neben dem Widget, das die Komponente selbst
     * einbindet.
     */
    public function test_cloudflare_is_loaded_only_once_per_request(): void
    {
        $html = Blade::render(
            '<x-turnstile-scripts action="company_listing" />'
            .'<x-turnstile-scripts action="registration" />'
            .'<x-turnstile action="company_listing" wire="turnstileToken" field="turnstileToken" />'
            .'@stack(\'scripts\')'
        );

        $this->assertStringContainsString('data-turnstile-root', $html);
        $this->assertSame(1, substr_count($html, 'challenges.cloudflare.com'));
    }

    /** Ist das Modul aus, wird Cloudflare auf der Seite nicht geladen. */
    public function test_nothing_is_loaded_when_the_module_is_off(): void
    {
        config()->set('turnstile.enabled', false);

        $html = Blade::render('<x-turnstile-scripts action="company_listing" />@stack(\'scripts\')');

        $this->assertStringNotContainsString('challenges.cloudflare.com', $html);
    }

    /**
     * Die eigentliche Absicherung: steht ein <x-turnstile /> in einer Ansicht
     * hinter einer Bedingung, dann erscheint es moeglicherweise erst nach einem
     * Livewire-Umlauf — und die Ansicht muss die Skripte unbedingt anfordern.
     * Faellt dieser Test, fehlt in der genannten Datei
     * `<x-turnstile-scripts action="..." />` ausserhalb der Bedingung.
     */
    public function test_every_conditionally_rendered_widget_requests_the_scripts(): void
    {
        $fehlend = [];

        foreach (File::allFiles(resource_path('views')) as $datei) {
            if (! str_ends_with($datei->getFilename(), '.blade.php')) {
                continue;
            }

            // Nur Livewire-Ansichten: dort kann die Bedingung nach dem ersten
            // Seitenaufbau umschlagen. Eine gewoehnliche Seite rendert ihre
            // Bedingung und das Layout im selben Durchlauf.
            if (! str_contains($datei->getPathname(), '/livewire/')) {
                continue;
            }

            $inhalt = File::get($datei->getPathname());

            if (! str_contains($inhalt, '<x-turnstile ')) {
                continue;
            }

            if (! $this->widgetStehtHinterBedingung($inhalt)) {
                continue;
            }

            if (! str_contains($inhalt, '<x-turnstile-scripts')) {
                $fehlend[] = str_replace(resource_path('views').'/', '', $datei->getPathname());
            }
        }

        $this->assertSame([], $fehlend, 'Bedingt eingeblendetes Turnstile-Widget ohne <x-turnstile-scripts />: '.implode(', ', $fehlend));
    }

    /**
     * Blade-Verschachtelung bis zur Widget-Zeile zaehlen. Nur Bedingungen
     * zaehlen: eine Schleife blendet nichts verzoegert ein, aber auch sie darf
     * das Ergebnis nicht verfaelschen, darum werden ihre Marken mitgezaehlt.
     */
    private function widgetStehtHinterBedingung(string $inhalt): bool
    {
        $tiefe = 0;

        foreach (explode("\n", $inhalt) as $zeile) {
            if (str_contains($zeile, '<x-turnstile ') && $tiefe > 0) {
                return true;
            }

            $tiefe += preg_match_all('/@(if|unless|isset|empty|auth|guest|foreach|forelse|for|while)\s*[(\s]/', $zeile);
            $tiefe -= preg_match_all('/@end(if|unless|isset|empty|auth|guest|foreach|forelse|for|while)\b/', $zeile);
        }

        return false;
    }
}
