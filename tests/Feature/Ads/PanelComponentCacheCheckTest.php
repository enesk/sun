<?php

declare(strict_types=1);

namespace Tests\Feature\Ads;

use App\Filament\Admin\Pages\AdPlacementEditor;
use App\Filament\Admin\Pages\AdSettings;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Feature\FeatureTest;

/**
 * Der Go-Live-Check muss einen veralteten Filament-Komponenten-Cache melden
 * (Ticket #64).
 *
 * Hintergrund: liegt `bootstrap/cache/filament/panels/admin.php`, liest
 * Filament **nur** diese Liste und durchsucht die Verzeichnisse nicht mehr.
 * Eine Panel-Seite, die danach dazugekommen ist, ist nicht registriert — ihre
 * Route steht in `route:list`, der Aufruf endet aber mit 404. Am 16.10.2026 war
 * `/admin/werbeplaetze` deshalb acht Tage unerreichbar, und `route:list` hat
 * den Fehler zweimal verdeckt.
 */
class PanelComponentCacheCheckTest extends FeatureTest
{
    /*
     * Geprueft wird die Ausgabe dieser einen Zeile, nicht der Exit-Code: der
     * Befehl endet in der Testumgebung schon wegen anderer Punkte mit 1 (etwa
     * CSP_MODE, das hier nicht gesetzt ist).
     *
     * Gelesen wird ueber einen BufferedOutput und nicht mit
     * `$this->artisan(...)->expectsOutputToContain(...)`: die Erwartungen dort
     * greifen auf dieser Zeile nicht zuverlaessig (zeilenweiser Vergleich auf
     * einer Zeile mit Farbmarkierungen und Spaltenauffuellung), und ein Test,
     * der aus dem falschen Grund rot wird, ist schlimmer als keiner.
     */
    private string $cacheDatei;

    private ?string $vorher = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheDatei = base_path('bootstrap/cache/filament/panels/admin.php');
        $this->vorher = is_file($this->cacheDatei) ? (string) file_get_contents($this->cacheDatei) : null;

        if (! is_dir(dirname($this->cacheDatei))) {
            mkdir(dirname($this->cacheDatei), 0775, true);
        }
    }

    protected function tearDown(): void
    {
        // Den Zustand der Arbeitskopie wiederherstellen: ein liegengebliebener
        // Cache wuerde jeden weiteren Lauf beeinflussen.
        $this->vorher === null
            ? @unlink($this->cacheDatei)
            : file_put_contents($this->cacheDatei, $this->vorher);

        parent::tearDown();
    }

    public function test_ohne_cache_ist_die_pruefung_in_ordnung(): void
    {
        @unlink($this->cacheDatei);

        $zeile = $this->pruefzeile();

        $this->assertStringContainsString('OK', $zeile);
        $this->assertStringContainsString('keiner, Filament durchsucht die Verzeichnisse', $zeile);
    }

    public function test_cache_ohne_werbe_seiten_ist_ein_fail(): void
    {
        // So sieht der Cache aus, wenn er vor dem Werbemodul gebaut wurde:
        // andere Klassen sind drin, die Werbe-Seiten fehlen.
        $this->cacheSchreiben(['App\\Filament\\Admin\\Pages\\GeneralSettings']);

        $zeile = $this->pruefzeile();

        $this->assertStringContainsString('FAIL', $zeile);
        $this->assertStringContainsString('AdPlacementEditor', $zeile);
        $this->assertStringContainsString('404', $zeile);
    }

    public function test_cache_mit_werbe_seiten_ist_in_ordnung(): void
    {
        $this->cacheSchreiben([AdPlacementEditor::class, AdSettings::class]);

        $zeile = $this->pruefzeile();

        $this->assertStringContainsString('OK', $zeile);
        $this->assertStringContainsString('Werbe-Seiten drin', $zeile);
    }

    /** Die Zeile des Go-Live-Checks zum Panel-Cache. */
    private function pruefzeile(): string
    {
        $ausgabe = new BufferedOutput;
        Artisan::call('ads:golive:check', ['--ohne-alt' => true], $ausgabe);

        foreach (explode("\n", $ausgabe->fetch()) as $zeile) {
            if (str_contains($zeile, 'Panel-Komponenten-Cache')) {
                return $zeile;
            }
        }

        $this->fail('Der Go-Live-Check nennt den Panel-Komponenten-Cache nicht.');
    }

    /**
     * Klassennamen stehen im Cache als PHP-Quelltext aus `var_export`, also mit
     * doppelten Backslashes. Genau daran ist die erste Fassung der Pruefung
     * vorbeigelaufen — der Test schreibt deshalb bewusst dieselbe Schreibweise.
     *
     * @param  list<string>  $klassen
     */
    private function cacheSchreiben(array $klassen): void
    {
        $zeilen = implode(",\n", array_map(
            fn (string $klasse): string => "    '".str_replace('\\', '\\\\', $klasse)."'",
            $klassen,
        ));

        file_put_contents($this->cacheDatei, "<?php\n\nreturn [\n  'pages' => [\n{$zeilen},\n  ],\n];\n");
        touch($this->cacheDatei, time() + 60);
    }
}
