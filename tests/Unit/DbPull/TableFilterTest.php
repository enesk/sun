<?php

declare(strict_types=1);

namespace Tests\Unit\DbPull;

use App\Services\DbPull\TableFilter;
use Tests\TestCase;

/**
 * Tabellenfilter: Struktur immer, Daten nach Liste (#4, #10).
 *
 * Der teure Fehler waere hier nicht eine zu grosse Datenmenge, sondern eine
 * lokal FEHLENDE Tabelle: Migrationen und Code laufen darauf auf. Die Tests
 * halten deshalb fest, dass der Filter nur ueber Daten entscheidet und dass
 * Muster gegen die Tabellen der Live-Datenbank aufgeloest werden — ein
 * `--ignore-table` mit einem Namen, den es auf Live nicht gibt, bricht den
 * Dump ab.
 */
class TableFilterTest extends TestCase
{
    /** Die Tabellen, die auf Live stehen — Grundlage jeder Aufloesung. */
    private const LIVE = [
        'companies',
        'reviews',
        'telescope_entries',
        'telescope_entries_tags',
        'jobs',
        'failed_jobs',
        'cache',
        'sessions',
        'company_events',
    ];

    public function test_muster_werden_gegen_die_live_tabellen_aufgeloest(): void
    {
        $filter = new TableFilter(skipPatterns: ['telescope_*', 'cache', 'sessions']);

        $this->assertSame(
            ['telescope_entries', 'telescope_entries_tags', 'cache', 'sessions'],
            $filter->skipData(self::LIVE),
        );
    }

    public function test_ein_muster_ohne_treffer_erzeugt_keinen_eintrag(): void
    {
        // Entscheidend fuer den Dump: ein Name, den es auf Live nicht gibt,
        // darf nie als --ignore-table weitergegeben werden.
        $filter = new TableFilter(skipPatterns: ['gibt_es_nicht', 'horizon_*']);

        $this->assertSame([], $filter->skipData(self::LIVE));
        $this->assertSame(self::LIVE, $filter->dataTables(self::LIVE));
    }

    public function test_namen_ohne_platzhalter_treffen_keine_nachbartabelle(): void
    {
        // 'jobs' darf nicht 'failed_jobs' mitnehmen — sonst fehlen Daten,
        // die niemand ausgeschlossen hat.
        $filter = new TableFilter(skipPatterns: ['jobs']);

        $this->assertSame(['jobs'], $filter->skipData(self::LIVE));
        $this->assertContains('failed_jobs', $filter->dataTables(self::LIVE));
    }

    public function test_daten_und_struktur_ergeben_zusammen_die_ganze_datenbank(): void
    {
        $filter = new TableFilter(skipPatterns: ['telescope_*', 'cache']);

        $zusammen = array_merge($filter->dataTables(self::LIVE), $filter->skipData(self::LIVE));

        sort($zusammen);
        $erwartet = self::LIVE;
        sort($erwartet);

        $this->assertSame($erwartet, $zusammen, 'Keine Tabelle darf aus beiden Listen fallen.');
    }

    public function test_only_tables_dreht_die_logik_um_und_schlaegt_die_skip_liste(): void
    {
        $filter = new TableFilter(
            skipPatterns: ['telescope_*', 'cache'],
            onlyTables: ['companies', 'reviews'],
        );

        $this->assertTrue($filter->hasOnlyTables());
        $this->assertSame(['companies', 'reviews'], $filter->dataTables(self::LIVE));

        // Alles andere kommt als Struktur — auch die Tabellen, die laut
        // Skip-Liste ohnehin keine Daten bekommen haetten.
        $skip = $filter->skipData(self::LIVE);
        $this->assertContains('jobs', $skip);
        $this->assertContains('telescope_entries', $skip);
        $this->assertNotContains('companies', $skip);
    }

    public function test_only_tables_erlaubt_platzhalter(): void
    {
        $filter = new TableFilter(onlyTables: ['company_*']);

        $this->assertSame(['company_events'], $filter->dataTables(self::LIVE));
    }

    public function test_unbekannte_only_tables_werden_gemeldet_statt_den_dump_abzubrechen(): void
    {
        $filter = new TableFilter(onlyTables: ['companies', 'gibt_es_nicht', 'auch_nicht_*']);

        $this->assertSame(['gibt_es_nicht', 'auch_nicht_*'], $filter->unknownOnlyTables(self::LIVE));
        $this->assertSame(['companies'], $filter->dataTables(self::LIVE));
    }

    public function test_laufzeitliste_ergaenzt_die_konfiguration_statt_sie_zu_ersetzen(): void
    {
        config(['db-pull.skip_data.tenant' => ['telescope_*']]);

        $filter = TableFilter::forScope('tenant', extraSkip: ['company_events']);

        $this->assertSame(
            ['telescope_*', 'company_events'],
            $filter->skipPatterns(),
            'Die Liste aus config/db-pull.php muss erhalten bleiben.',
        );
        $this->assertSame(
            ['telescope_entries', 'telescope_entries_tags', 'company_events'],
            $filter->skipData(self::LIVE),
        );
    }

    public function test_die_vorgabe_haelt_protokoll_und_cache_tabellen_von_den_daten_fern(): void
    {
        // Gegen die echte Konfiguration: die Haelfte des Live-Volumens sind
        // Protokolltabellen, sie duerfen nicht versehentlich mitkommen.
        foreach (['central', 'tenant'] as $scope) {
            $muster = TableFilter::forScope($scope)->skipPatterns();

            $this->assertNotSame([], $muster, "Fuer {$scope} fehlt die Skip-Liste in config/db-pull.php.");
        }

        $tenant = TableFilter::forScope('tenant');
        $skip = $tenant->skipData(self::LIVE);

        foreach (['telescope_entries', 'cache', 'sessions', 'failed_jobs'] as $tabelle) {
            $this->assertContains($tabelle, $skip, "Von {$tabelle} sollten keine Daten geholt werden.");
        }

        $this->assertContains('companies', $tenant->dataTables(self::LIVE), 'Fachdaten muessen kommen.');
    }
}
