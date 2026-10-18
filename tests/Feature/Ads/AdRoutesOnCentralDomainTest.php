<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

/**
 * Die Werberouten muessen auch auf der zentralen Domain antworten.
 *
 * Grund des Tests: `CENTRAL_DOMAIN` ist auf der Produktion
 * `sanitaerfinden.com` — und diese Domain ist **gleichzeitig** ein
 * ausgeliefertes Portal (Tenant 28). `PreventAccessFromCentralDomains` hat
 * dort beide Werberouten mit 404 abgewiesen:
 *
 * * `POST /_ads/impressions` — der Beacon. `navigator.sendBeacon` wertet
 *   keine Antwort aus, die Impressionen dieses Portals fielen also **still**
 *   aus. Aufgefallen ist es nur, weil der Fehler in der Browserkonsole stand.
 * * `GET /_ads/html/{adUnit}` — die Zustellung eigener HTML-Werbemittel.
 *   Noch ohne Folge, weil zentral kein `html`-Werbemittel angelegt ist.
 *
 * Die Seitenrouten des Portals fahren aus demselben Grund `universal`. Dieser
 * Test haelt den Gleichlauf fest: wer die Sperre zurueckholt, nimmt dem
 * Portal auf der zentralen Domain unbemerkt die Zaehlung.
 */
class AdRoutesOnCentralDomainTest extends TestCase
{
    /** @return list<array{0: string}> */
    public static function werberouten(): array
    {
        return [
            ['portal.ads.impressions'],
            ['portal.ads.html'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('werberouten')]
    public function test_die_route_laeuft_universal_und_nicht_zentral_gesperrt(string $name): void
    {
        $route = Route::getRoutes()->getByName($name);

        $this->assertNotNull($route, "Route {$name} ist nicht registriert.");

        $middleware = $route->gatherMiddleware();

        $this->assertContains(
            'universal',
            $middleware,
            "Route {$name} fuehrt kein `universal` — auf der zentralen Domain, die selbst ein ".
            'Portal ausliefert, antwortet sie dann mit 404.',
        );

        $this->assertNotContains(
            PreventAccessFromCentralDomains::class,
            $middleware,
            "Route {$name} sperrt die zentrale Domain aus. Dort laeuft ein Portal — ".
            'beim Beacon verschwinden die Impressionen dabei still, weil sendBeacon keine '.
            'Antwort auswertet.',
        );
    }

    /** Gegenstueck: eine Seitenroute des Portals faehrt schon immer universal. */
    public function test_eine_seitenroute_faehrt_ebenfalls_universal(): void
    {
        $route = Route::getRoutes()->getByName('portal.companies.index');

        $this->assertNotNull($route);
        $this->assertContains('universal', $route->gatherMiddleware());
    }
}
