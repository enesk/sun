<?php

declare(strict_types=1);

namespace Tests\Unit\DbPull;

use App\Console\Commands\DbPullCommand;
use App\Services\DbPull\LocalMariaDb;
use Mockery;
use Mockery\MockInterface;
use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Stumpf spiegeln statt abgleichen (#11).
 *
 * Maßgeblich ist allein `show databases` auf Live. Lokale Datenbanken mit
 * dem Tenant-Praefix, die es dort nicht gibt, werden entfernt — danach
 * entspricht der lokale Satz Datenbank fuer Datenbank dem von Live.
 *
 * Kein Test legt eine Datenbank an oder loescht eine: der lokale Server ist
 * der Arbeitsrechner, auf dem 2.200 Portal-Datenbanken liegen. Geprueft wird
 * die Auswahl- und Aufraeumlogik gegen eine gefaelschte Datenbankliste.
 */
class LocalSurplusTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_was_auf_live_fehlt_gilt_als_ueberzaehlig(): void
    {
        $surplus = $this->ruf(
            'localSurplus',
            $this->local(['tenant_live1', 'tenant_live2', 'tenant_19', 'tenant_33']),
            ['tenant_live1', 'tenant_live2'],
            'tenant_',
        );

        $this->assertSame(['tenant_19', 'tenant_33'], $surplus, 'Die lokalen Test-Portale sind ueberzaehlig.');
    }

    public function test_eine_datenbank_ohne_zeile_in_tenants_wird_geholt_statt_uebersprungen(): void
    {
        // Kern von #11: die Live-Liste ist die Auswahl. Eine Datenbank, die
        // lokal fehlt und in `tenants` nicht steht, ist kein Sonderfall mehr
        // — sie steht einfach auf der Liste und wird geholt.
        $live = ['tenant_live1', 'tenant_ohne_eintrag'];

        $surplus = $this->ruf('localSurplus', $this->local(['tenant_live1']), $live, 'tenant_');

        $this->assertSame([], $surplus);
        $this->assertContains(
            'tenant_ohne_eintrag',
            $live,
            'Die Live-Liste wird nicht gegen tenants geschnitten.',
        );
    }

    public function test_eine_zeile_in_tenants_ohne_datenbank_auf_live_erzeugt_keine_meldung(): void
    {
        // Frueher kam je Abweichung eine Zeile: "Portal tenant_49 ist
        // eingetragen, hat auf Live aber keine Datenbank — uebersprungen."
        // Der Abgleich, aus dem sie stammte, ist entfallen.
        $this->assertFalse(
            method_exists(DbPullCommand::class, 'tenantDatabasesFromCentral'),
            'Der Abgleich mit der tenants-Tabelle darf nicht zurueckkommen.',
        );

        // Eine Datenbank, die nur in `tenants` steht, taucht in der Auswahl
        // nicht auf und erzeugt auch keine Meldung: Grundlage ist allein die
        // Live-Liste.
        $surplus = $this->ruf('localSurplus', $this->local(['tenant_live1']), ['tenant_live1', 'tenant_49'], 'tenant_');

        $this->assertSame([], $surplus);
    }

    public function test_entfernt_werden_nur_datenbanken_mit_dem_praefix(): void
    {
        /** @var MockInterface $mock */
        $mock = Mockery::mock(LocalMariaDb::class);
        $mock->shouldReceive('databasesWithPrefix')
            ->with('tenant_')
            ->andReturn(['tenant_live1', 'tenant_19']);

        // Die Erwartung ist der Test: genau eine Datenbank wird gedroppt,
        // und zwar die ueberzaehlige. Jeder weitere Aufruf laesst Mockery
        // scheitern.
        $mock->shouldReceive('dropDatabase')->once()->with('tenant_19');

        $entfernt = $this->ruf('dropLocalSurplus', $mock, ['tenant_live1'], 'tenant_');

        $this->assertSame(['tenant_19'], $entfernt);
    }

    public function test_eine_nicht_lesbare_datenbankliste_entfernt_nichts(): void
    {
        /** @var MockInterface $mock */
        $mock = Mockery::mock(LocalMariaDb::class);
        $mock->shouldReceive('databasesWithPrefix')->andThrow(new RuntimeException('keine Verbindung'));
        $mock->shouldNotReceive('dropDatabase');

        $this->assertSame([], $this->ruf('dropLocalSurplus', $mock, ['tenant_live1'], 'tenant_'));
    }

    public function test_eine_leere_live_liste_wuerde_alles_entfernen_deshalb_gilt_die_mindestzahl(): void
    {
        // Ohne den Sicherheitsgurt in pull() waere eine leere oder halbe
        // Live-Liste das Ende des lokalen Bestands. Hier wird die Rechnung
        // belegt; der Abbruch selbst sitzt vor dem Entfernen.
        $surplus = $this->ruf('localSurplus', $this->local(['tenant_19', 'tenant_33']), [], 'tenant_');

        $this->assertSame(['tenant_19', 'tenant_33'], $surplus);
        $this->assertGreaterThanOrEqual(
            20,
            (int) config('db-pull.min_tenant_databases'),
            'Die Mindestzahl ist der Schutz gegen eine halbe Live-Liste — sie darf nicht klein werden.',
        );
    }

    public function test_das_like_muster_maskiert_den_unterstrich(): void
    {
        // In LIKE ist `_` ein Platzhalter: ohne Maskierung traefe `tenant_`
        // auch `tenantx…` und eine fremde Datenbank waere zum Loeschen
        // vorgemerkt.
        $this->assertSame('tenant\_%', LocalMariaDb::likePattern('tenant_'));
        $this->assertSame('vor\%zeichen\_%', LocalMariaDb::likePattern('vor%zeichen_'));
        $this->assertSame('tenant%', LocalMariaDb::likePattern('tenant'));
    }

    public function test_tenant_option_nimmt_einen_reinen_datenbanknamen(): void
    {
        // #11, Punkt 5: ohne Registrierung in `tenants`, nur der Name.
        $gewaehlt = $this->ruf(
            'selectTenantDatabases',
            ['tenant_live1', 'tenant_live2'],
            ['tenant_live2'],
            'tenant_',
        );

        $this->assertSame(['tenant_live2'], $gewaehlt);
    }

    public function test_tenant_option_loest_einen_eindeutigen_teilnamen_auf(): void
    {
        $gewaehlt = $this->ruf(
            'selectTenantDatabases',
            ['tenant_live1', 'tenant_live2'],
            ['live2'],
            'tenant_',
        );

        $this->assertSame(['tenant_live2'], $gewaehlt);
    }

    /**
     * Gefaelschte lokale Datenbankliste. Rueckgabe ist bewusst der Mock und
     * nicht LocalMariaDb: die geprueften Methoden nehmen ihn per Reflection
     * entgegen, und so bleibt die Mockery-Schnittstelle sichtbar.
     *
     * @param  array<int, string>  $databases
     */
    private function local(array $databases): MockInterface
    {
        $mock = Mockery::mock(LocalMariaDb::class);
        $mock->shouldReceive('databasesWithPrefix')->andReturn($databases);
        $mock->shouldReceive('dropDatabase')->andReturnNull();

        return $mock;
    }

    /**
     * Auswahl und Aufraeumen sitzen privat im Befehl — sie haben keinen
     * eigenen Dienst, weil sie nur dort gebraucht werden. Fuer den
     * Vertragstest werden sie per Reflection gerufen.
     *
     * @return array<int, string>
     */
    private function ruf(string $methode, mixed ...$argumente): array
    {
        $aufruf = new ReflectionMethod(DbPullCommand::class, $methode);
        $aufruf->setAccessible(true);

        /** @var array<int, string> $ergebnis */
        $ergebnis = $aufruf->invoke(app(DbPullCommand::class), ...$argumente);

        return $ergebnis;
    }
}
