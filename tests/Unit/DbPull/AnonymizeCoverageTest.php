<?php

declare(strict_types=1);

namespace Tests\Unit\DbPull;

use App\Services\DbPull\TableFilter;
use Tests\TestCase;

/**
 * Waechter gegen das lautlose Veralten der Anonymisierung (#5, #10).
 *
 * Der Anonymisierer arbeitet nach einer Liste in config/db-pull.php. Kommt
 * eine neue Spalte mit Personenbezug dazu, faellt das ohne Pruefung niemandem
 * auf — bis Live-Daten unmaskiert auf einem Laptop liegen. Dieser Test liest
 * deshalb die Migrationen, sucht Spaltennamen mit Personenbezug und verlangt
 * fuer jede einen der drei Nachweise:
 *
 * 1. eine Regel in `anonymize_rules`,
 * 2. die Tabelle steht in `anonymize_truncate` (wird geleert),
 * 3. die Tabelle bekommt laut `skip_data` ohnehin keine Daten,
 *
 * oder einen Eintrag in {@see self::BEWUSST_IM_KLARTEXT} mit Begruendung.
 * Schlaegt der Test an, ist das keine Formalie: entweder gehoert die Spalte
 * in die Regeln, oder die Entscheidung dagegen gehoert hier dokumentiert.
 */
class AnonymizeCoverageTest extends TestCase
{
    /** Spaltennamen, die auf Personenbezug hindeuten. */
    private const VERDAECHTIG = '/(email|e_mail|mail_address|phone|^tel$|telefon|mobile|ip_address|_ip$|'
        .'user_agent|token|secret|password|iban|tax_number|first_name|last_name|contact_name|'
        .'author_name|notes|street|house_no)/i';

    /**
     * Bewusst nicht anonymisiert, mit Grund. Wer hier etwas ergaenzt,
     * trifft eine Entscheidung — deshalb steht der Grund daneben.
     *
     * @var array<string, string> "<scope>.<tabelle>.<spalte>" => Grund
     */
    private const BEWUSST_IM_KLARTEXT = [
        // Geschaeftsanschrift eines Betriebs. Sie steht oeffentlich im
        // Portal und im Impressum des Betriebs; maskiert waeren Stadtseiten,
        // Entfernungen und Kartenausschnitte lokal nicht mehr pruefbar.
        'tenant.companies.street' => 'oeffentliche Geschaeftsanschrift, im Portal sichtbar',
        'tenant.companies.house_no' => 'oeffentliche Geschaeftsanschrift, im Portal sichtbar',

        // Name der Redaktion unter den Ratgeber-Artikeln, steht so auf jeder
        // Artikelseite und im Impressum.
        'tenant.tenant_content_settings.author_name' => 'oeffentlicher Autorname der Redaktion',
        'tenant.tenant_guide_settings.author_name' => 'oeffentlicher Autorname der Redaktion',

        // Redaktionsnotizen zu einem Thema: Arbeitsnotizen zur Gliederung,
        // keine Personendaten. Trifft das Muster nur ueber das Wort "notes".
        'central.guide_topic_list_items.notes' => 'Arbeitsnotiz zum Thema, kein Personenbezug',
        'tenant.guide_topics.notes' => 'Arbeitsnotiz zum Thema, kein Personenbezug',
    ];

    public function test_jede_spalte_mit_personenbezug_ist_abgedeckt_oder_begruendet(): void
    {
        $offen = [];

        foreach (['central', 'tenant'] as $scope) {
            $rules = (array) config("db-pull.anonymize_rules.{$scope}", []);
            $json = (array) config("db-pull.anonymize_json.{$scope}", []);
            $truncate = (array) config("db-pull.anonymize_truncate.{$scope}", []);
            $filter = TableFilter::forScope($scope);

            foreach ($this->verdaechtigeSpalten($scope) as $tabelle => $spalten) {
                // Tabellen ohne Daten im Pull brauchen keine Regel.
                if ($filter->skipData([$tabelle]) === [$tabelle]) {
                    continue;
                }

                if (in_array($tabelle, $truncate, true)) {
                    continue;
                }

                foreach ($spalten as $spalte) {
                    if (isset($rules[$tabelle][$spalte]) || isset($json[$tabelle][$spalte])) {
                        continue;
                    }

                    if (isset(self::BEWUSST_IM_KLARTEXT["{$scope}.{$tabelle}.{$spalte}"])) {
                        continue;
                    }

                    $offen[] = "{$scope}: {$tabelle}.{$spalte}";
                }
            }
        }

        $this->assertSame([], $offen, implode("\n", array_merge(
            ['Spalten mit Personenbezug ohne Anonymisierung:'],
            $offen,
            [
                '',
                'Entweder eine Regel in config/db-pull.php (anonymize_rules/anonymize_json/',
                'anonymize_truncate/skip_data) ergaenzen — oder, wenn der Klartext gewollt ist,',
                'einen begruendeten Eintrag in AnonymizeCoverageTest::BEWUSST_IM_KLARTEXT.',
            ],
        )));
    }

    public function test_die_ausnahmeliste_enthaelt_keine_veralteten_eintraege(): void
    {
        // Eine Ausnahme fuer eine Spalte, die es nicht mehr gibt, verdeckt
        // beim naechsten Mal eine echte Luecke.
        foreach (self::BEWUSST_IM_KLARTEXT as $schluessel => $grund) {
            [$scope, $tabelle, $spalte] = explode('.', $schluessel, 3);

            $this->assertNotSame('', trim($grund), "Ausnahme {$schluessel} ohne Begruendung.");
            $this->assertArrayHasKey(
                $tabelle,
                $this->verdaechtigeSpalten($scope),
                "Ausnahme {$schluessel} zeigt auf eine Tabelle, die es nicht mehr gibt.",
            );
            $this->assertContains(
                $spalte,
                $this->verdaechtigeSpalten($scope)[$tabelle],
                "Ausnahme {$schluessel} zeigt auf eine Spalte, die es nicht mehr gibt.",
            );
        }
    }

    public function test_die_regeln_treffen_die_bekannten_zugangsmittel(): void
    {
        // Tokens, Passwoerter und Zwei-Faktor-Geheimnisse sind der Grund,
        // weshalb die Anonymisierung Vorgabe ist und nicht Zusatz.
        $central = (array) config('db-pull.anonymize_rules.central', []);

        $this->assertSame(':password_hash', $central['users']['password'] ?? null);
        $this->assertSame('NULL', $central['users']['remember_token'] ?? null);
        $this->assertSame('NULL', $central['users']['two_factor_secret'] ?? null);
        $this->assertSame('NULL', $central['content_users']['remember_token'] ?? null);
    }

    /**
     * Spaltennamen mit Personenbezug je Bereich, aus den Migrationen
     * gelesen. Die Migrationen sind die einzige Quelle des Schemas — ein
     * Abgleich gegen eine Testdatenbank wuerde die Tenant-Tabellen nicht
     * sehen, die dort gar nicht angelegt sind.
     *
     * @return array<string, array<int, string>>
     */
    private function verdaechtigeSpalten(string $scope): array
    {
        $verzeichnis = $scope === 'tenant'
            ? base_path('database/migrations/tenant')
            : base_path('database/migrations');

        $gefunden = [];

        foreach (glob($verzeichnis.'/*.php') ?: [] as $datei) {
            $tabelle = null;

            foreach (explode("\n", (string) file_get_contents($datei)) as $zeile) {
                if (preg_match("/Schema::(?:create|table)\(\s*'([a-z0-9_]+)'/i", $zeile, $treffer) === 1) {
                    $tabelle = $treffer[1];
                }

                if ($tabelle === null) {
                    continue;
                }

                if (preg_match('/->rememberToken\(\)/', $zeile) === 1) {
                    $gefunden[$tabelle]['remember_token'] = true;
                }

                if (preg_match(
                    "/->(?:string|text|char|json|longText|mediumText|ipAddress)\(\s*'([a-z0-9_]+)'/i",
                    $zeile,
                    $treffer
                ) === 1 && preg_match(self::VERDAECHTIG, $treffer[1]) === 1) {
                    $gefunden[$tabelle][$treffer[1]] = true;
                }
            }
        }

        return array_map(
            fn (array $spalten): array => array_keys($spalten),
            $gefunden,
        );
    }
}
