<?php

declare(strict_types=1);

namespace Tests\Feature\Themes;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\FeatureTest;

/**
 * Nicht ausgelieferte Portale (#16).
 *
 * Zwei Domains zeigen an der Cloudflare-Kante auf ein anderes Angebot. Die
 * Tenants bleiben im System, duerfen aber in keiner Arbeitsliste mehr als
 * offene Aufgabe erscheinen. Maßgeblich ist das Attribut am Tenant — eine
 * Liste in einer Konfigurationsdatei waere eine zweite Quelle.
 */
class NotDeliveredTenantTest extends FeatureTest
{
    use DatabaseTransactions;

    public function test_ein_tenant_gilt_als_ausgeliefert_solange_das_attribut_fehlt(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'probe-'.bin2hex(random_bytes(3)).'.test']);

        $this->assertTrue($tenant->isDelivered());
        $this->assertNull($tenant->notDeliveredNote());
    }

    public function test_das_attribut_schaltet_ein_portal_aus_der_auslieferung(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'probe-'.bin2hex(random_bytes(3)).'.test']);

        $tenant->setAttribute(Tenant::NOT_DELIVERED, true);
        $tenant->setAttribute(Tenant::NOT_DELIVERED_NOTE, 'Testfall (#16)');
        $tenant->save();

        $frisch = Tenant::find($tenant->getKey());

        $this->assertFalse($frisch->isDelivered());
        $this->assertSame('Testfall (#16)', $frisch->notDeliveredNote());
    }

    public function test_der_statusbefehl_fuehrt_das_portal_als_nicht_ausgeliefert(): void
    {
        $tenant = Tenant::factory()->create(['domain' => 'probe-nicht-ausgeliefert.test']);
        $tenant->setAttribute(Tenant::NOT_DELIVERED, true);
        $tenant->setAttribute(Tenant::NOT_DELIVERED_NOTE, 'Kante leitet weiter (#16)');
        $tenant->save();

        $this->artisan('sun:branches:status')
            ->expectsOutputToContain('nicht ausgeliefert')
            ->assertExitCode(0);
    }

    public function test_der_schluesselcheck_rechnet_die_ausgelieferten_hostnames_selbst(): void
    {
        // #16: die Cloudflare-Widgets bleiben unveraendert, auch wenn ein
        // Portal wegfaellt. Die Zahl darf deshalb nicht im Code stehen,
        // sondern muss gerechnet werden — sonst meldet der Befehl dauerhaft
        // einen Unterschied zum Widget.
        // Der Exit-Code haengt lokal an den fehlenden Schluesseln der Gruppen
        // B und C (dort stehen nur die Testschluessel der Gruppe A) — geprueft
        // wird deshalb die Rechnung, nicht der Code.
        $this->artisan('turnstile:keys:check')
            ->expectsOutputToContain('davon ausgeliefert')
            ->run();

        $this->assertStringNotContainsString(
            'Soll laut docs/turnstile.md Abschnitt 8: 18',
            (string) file_get_contents(base_path('app/Console/Commands/TurnstileKeysCheck.php')),
            'Die Soll-Zahl darf nicht mehr fest im Code stehen.',
        );
    }
}
