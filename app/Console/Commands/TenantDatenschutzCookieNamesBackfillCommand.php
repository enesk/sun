<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Korrigiert bei Bestandstenants die Cookie-Tabelle im Abschnitt
 * "Technisch notwendige Cookies" (#30): dort steht ein Cookie
 * `cookie_consent`, gesetzt werden tatsaechlich `cookie_consent_given`,
 * `cookie_consent_statistics`, `cookie_consent_marketing` und — als Ersatz bei
 * gesperrtem localStorage — `cookie_consent_preferences`.
 *
 * Arbeitet wie tenants:datenschutz:turnstile-backfill (#11) auf dem Rohtext
 * unter branding.datenschutz und schreibt ueber TenantBrandingService::set().
 *
 * Trockenlauf ist Vorgabe, geschrieben wird nur mit --write.
 *
 * Angefasst wird nur die eine Tabellenzeile und nur dann, wenn Zweck und Dauer
 * noch wortgleich dem Standardtext entsprechen. Jede abweichend gepflegte
 * Zeile wird gemeldet statt ueberschrieben.
 *
 * Der Wortlaut ist wortgleich mit TenantLegalDefaults::DATENSCHUTZ
 * (Abschnitt 8.1) — Textaenderungen immer in beiden Dateien.
 */
class TenantDatenschutzCookieNamesBackfillCommand extends Command
{
    protected $signature = 'tenants:datenschutz:cookie-names-backfill
        {--write : Aenderungen speichern (ohne nur Trockenlauf)}';

    protected $description = 'Korrigiert bei Bestandstenants die Cookie-Namen in der Datenschutz-Tabelle (#30)';

    /** Erwarteter Zweck der Altzeile — nur so wird sie ersetzt. */
    private const ALT_ZWECK = 'speicherung ihrer cookie-präferenzen';

    /** Erwartete Dauer der Altzeile — nur so wird sie ersetzt. */
    private const ALT_DAUER = '12 monate';

    /** Wortlaut wie in TenantLegalDefaults::DATENSCHUTZ (Abschnitt 8.1). */
    private const NAMEN = 'cookie_consent_* (cookie_consent_given, cookie_consent_statistics, cookie_consent_marketing, cookie_consent_preferences)';

    private const ZWECK = 'Speicherung Ihrer Cookie-Entscheidung (erteilt, Statistik, Marketing) und Ihrer Präferenzen';

    private const DAUER = '12 Monate';

    private const MONTHS = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    public function handle(TenantBrandingService $branding): int
    {
        $write = (bool) $this->option('write');

        if (! $write) {
            $this->comment('Trockenlauf — es wird nichts geschrieben. Speichern mit --write.');
            $this->newLine();
        }

        $counts = [];
        $manual = [];

        foreach (Tenant::all() as $tenant) {
            $name = (string) $tenant->getAttribute('name');
            $raw = $tenant->getAttribute(TenantConfigConstants::DATENSCHUTZ);

            if (! is_string($raw) || trim($raw) === '') {
                $result = 'übersprungen — kein Datenschutztext';
                $manual[] = "{$name} ({$result})";
            } else {
                [$text, $result] = $this->apply($raw);

                if ($text !== $raw && $write) {
                    $branding->set($tenant, TenantConfigConstants::DATENSCHUTZ, $text);
                }

                if (str_starts_with($result, 'unklar')) {
                    $manual[] = "{$name} ({$result})";
                }
            }

            $counts[$result] = ($counts[$result] ?? 0) + 1;
            $this->line("  {$name}: {$result}");
        }

        $this->newLine();
        $this->info('Zusammenfassung');
        ksort($counts);
        foreach ($counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        if ($manual !== []) {
            $this->newLine();
            $this->warn('Bitte von Hand ansehen:');
            foreach ($manual as $line) {
                $this->line("  - {$line}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string} Rohtext und Befund
     */
    private function apply(string $text): array
    {
        if (str_contains($text, 'cookie_consent_given')) {
            return [$text, 'vorhanden'];
        }

        $row = '/(?<indent>[ \t]*)<tr\b[^>]*>\s*<td\b[^>]*>\s*cookie_consent\s*<\/td>\s*<td\b[^>]*>(?<zweck>[^<]*)<\/td>\s*<td\b[^>]*>(?<dauer>[^<]*)<\/td>\s*<\/tr>/iu';

        if (preg_match($row, $text, $match) !== 1) {
            return [$text, 'unklar — Zeile „cookie_consent“ nicht gefunden'];
        }

        if ($this->normalize($match['zweck']) !== self::ALT_ZWECK || $this->normalize($match['dauer']) !== self::ALT_DAUER) {
            return [$text, 'unklar — Zeile abweichend gepflegt'];
        }

        $indent = $match['indent'];
        $inner = $indent.'    ';
        $neu = $indent."<tr>\n"
            .$inner.'<td>'.self::NAMEN."</td>\n"
            .$inner.'<td>'.self::ZWECK."</td>\n"
            .$inner.'<td>'.self::DAUER."</td>\n"
            .$indent.'</tr>';

        $text = str_replace($match[0], $neu, $text);

        return [$this->updateStand($text), 'Cookie-Namen korrigiert'];
    }

    private function updateStand(string $text): string
    {
        $stand = self::MONTHS[(int) now()->format('n')].' '.now()->format('Y');

        return (string) preg_replace(
            '/(<strong>\s*Stand:\s*<\/strong>\s*)[^<]+/u',
            '${1}'.$stand,
            $text,
            1,
        );
    }

    /**
     * Kleinschreibung, normalisierter Leerraum — nur fuer die Erkennung.
     */
    private function normalize(string $text): string
    {
        $text = str_replace(["\u{00A0}", '&nbsp;'], ' ', $text);
        $text = (string) preg_replace('/\s+/u', ' ', $text);

        return mb_strtolower(trim($text));
    }
}
