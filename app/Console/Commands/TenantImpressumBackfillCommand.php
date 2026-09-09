<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Zieht die beiden Impressums-Bausteine aus #31 bei Bestandstenants nach (#38).
 *
 * Eine Aenderung am Standardtext in CreateTenantCommand erreicht nur neu
 * angelegte Tenants — der Text bestehender Portale liegt als Rohtext unter
 * branding.impressum. Dieses Kommando arbeitet auf genau diesem Rohtext, also
 * vor der Platzhalteraufloesung in TenantBrandingService::resolveLegalPlaceholders();
 * die Platzhalter bleiben im gespeicherten Text stehen.
 *
 * Fehlende Bausteine werden ans Textende angehaengt statt an inhaltlich
 * passender Stelle eingefuegt: der Bestandstext ist freies, je Tenant
 * gepflegtes HTML, und jede Positionsheuristik zerlegt frueher oder spaeter
 * fremde Formatierungen.
 *
 * Tenants ohne hinterlegten Impressumstext werden bewusst nur gemeldet, nicht
 * befuellt — einen vollstaendigen Impressumstext zu erzeugen ist eine
 * rechtliche Zusage, die ein Nachzugslauf nicht treffen darf.
 */
class TenantImpressumBackfillCommand extends Command
{
    protected $signature = 'tenants:impressum:backfill
        {--dry-run : Nur melden, nichts schreiben}';

    protected $description = 'Ergaenzt bei Bestandstenants den Abschnitt "Redaktionell verantwortlich" (§ 18 Abs. 2 MStV) und den KI-Hinweis im Impressum';

    /**
     * Baustein A aus Abschnitt 2 der Vorgabe. Wortlaut ist normativ.
     */
    private const BLOCK_RESPONSIBLE = <<<'HTML'
<h2>Redaktionell verantwortlich gemäß § 18 Abs. 2 MStV</h2>
<p>
    <strong>[VERANTWORTLICH_NAME]</strong><br />
    [BETREIBER_STRASSE]<br />
    [BETREIBER_PLZ] [BETREIBER_ORT]
</p>
HTML;

    /**
     * Baustein B aus Abschnitt 3 der Vorgabe. Wortlaut ist normativ.
     */
    private const BLOCK_AI_NOTICE = <<<'HTML'
<h2>Hinweis zu KI-gestützten Inhalten</h2>
<p>
    Die Ratgeber-Artikel auf <strong>[PORTAL_NAME]</strong> werden mit Unterstützung von KI
    erstellt und vor der Veröffentlichung redaktionell geprüft. Verantwortlich für alle
    veröffentlichten Inhalte bleibt [BETREIBER_NAME]. Wie unsere Artikel entstehen, beschreiben
    wir unter <a href="/ratgeber/redaktion">So arbeitet unsere Redaktion</a>.
</p>
HTML;

    public function handle(TenantBrandingService $branding): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Trockenlauf — es wird nichts geschrieben.');
            $this->newLine();
        }

        $counts = [];
        $skipped = [];

        foreach (Tenant::all() as $tenant) {
            $name = (string) $tenant->name;
            $raw = $tenant->getAttribute(TenantConfigConstants::IMPRESSUM);

            if (! is_string($raw) || trim($raw) === '') {
                $skipped[] = $name;
                $counts['übersprungen — kein Impressumstext'] = ($counts['übersprungen — kein Impressumstext'] ?? 0) + 1;
                $this->line("  {$name}: übersprungen — kein Impressumstext");

                continue;
            }

            [$text, $resultA] = $this->applyResponsibleBlock($raw);
            [$text, $resultB] = $this->applyAiNoticeBlock($text);

            $counts["A: {$resultA}"] = ($counts["A: {$resultA}"] ?? 0) + 1;
            $counts["B: {$resultB}"] = ($counts["B: {$resultB}"] ?? 0) + 1;

            if ($text !== $raw && ! $dryRun) {
                $branding->set($tenant, TenantConfigConstants::IMPRESSUM, $text);
            }

            $this->line("  {$name}: A {$resultA}, B {$resultB}");
        }

        $this->newLine();
        $this->info('Zusammenfassung');
        ksort($counts);
        foreach ($counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        if ($skipped !== []) {
            $this->newLine();
            $this->warn('Ohne hinterlegten Impressumstext (bitte von Hand ansehen):');
            foreach ($skipped as $name) {
                $this->line("  - {$name}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string} Rohtext und Befund
     */
    private function applyResponsibleBlock(string $text): array
    {
        if (str_contains($this->normalize($text), '18 abs. 2 mstv')) {
            return [$text, 'vorhanden'];
        }

        $heading = '/(<h([1-6])\b[^>]*>)\s*Redaktionell\s+verantwortlich\s*(<\/h\2>)/iu';

        if (preg_match($heading, $text) === 1) {
            $replaced = preg_replace_callback(
                $heading,
                fn (array $m): string => $m[1].'Redaktionell verantwortlich gemäß § 18 Abs. 2 MStV'.$m[3],
                $text,
                1
            );

            return [(string) $replaced, 'Überschrift ergänzt'];
        }

        // Die Wendung steht im Text, aber nicht als Ueberschrift: hier laesst
        // sich weder sicher ergaenzen noch anhaengen, ohne einen zweiten
        // Abschnitt zu erzeugen. Solche Faelle gehen an die Redaktion.
        if (str_contains($this->normalize($text), 'redaktionell verantwortlich')) {
            return [$text, 'unklar — von Hand prüfen'];
        }

        return [$this->append($text, self::BLOCK_RESPONSIBLE), 'angehängt'];
    }

    /**
     * @return array{0: string, 1: string} Rohtext und Befund
     */
    private function applyAiNoticeBlock(string $text): array
    {
        $normalized = $this->normalize($text);

        if (str_contains($normalized, 'ki-gestützten inhalten') || str_contains($normalized, 'mit unterstützung von ki')) {
            return [$text, 'vorhanden'];
        }

        return [$this->append($text, self::BLOCK_AI_NOTICE), 'angehängt'];
    }

    private function append(string $text, string $block): string
    {
        return rtrim($text)."\n\n".$block."\n";
    }

    /**
     * Kleinschreibung, normalisierter Leerraum — nur fuer die Erkennung.
     */
    private function normalize(string $text): string
    {
        $text = str_replace(["\u{00A0}", '&nbsp;'], ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return mb_strtolower($text);
    }
}
