<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Ersetzt den fest eingetragenen Betreiber-Adressblock in den Rechtstexten von
 * Bestandstenants durch die Platzhalter aus TenantBrandingService (#48/#39).
 *
 * Bewusst ein eigenes Kommando neben TenantImpressumBackfillCommand (#38):
 * jenes haengt fehlende Abschnitte ans Textende an, hier wird ein vorhandener
 * Block ersetzt. Zwei gegenlaeufige Operationen in einem Trockenlauf sind nicht
 * mehr lesbar.
 *
 * Gearbeitet wird auf dem Rohtext der Tenant-Konfiguration, also vor
 * TenantBrandingService::resolveLegalPlaceholders(); die Platzhalter bleiben im
 * gespeicherten Text stehen.
 *
 * Ersetzt wird ausschliesslich der exakt bekannte Adressblock (Erkennung
 * case-insensitiv auf normalisiertem Leerraum). Weicht der Text ab, wird der
 * Tenant nur gemeldet — handgepflegte Adressblöcke werden nie ueberschrieben.
 * Tenants ohne Text werden ebenfalls nur gemeldet, das Befuellen ist #46.
 */
class TenantLegalDepersonalizeCommand extends Command
{
    protected $signature = 'tenants:legal:depersonalize
        {--dry-run : Nur melden, nichts schreiben}';

    protected $description = 'Ersetzt den festen Betreiber-Adressblock in Impressum und Datenschutz durch Platzhalter';

    /**
     * Der bekannte feste Adressblock als Regex-Fragment. Leerraum flexibel,
     * Wortlaut normativ.
     */
    private const FRAGMENT_ADDRESS = '(?P<indent>[ \t]*)<strong>\s*Rolland\s+Szalai\s*<\/strong>\s*<br\s*\/?>\s*Karlsruherstr\.\s*31\s*<br\s*\/?>\s*76437\s+Rastatt\s*<br\s*\/?>';

    /**
     * Derselbe Block, aber im Abschnitt "Redaktionell verantwortlich": dort
     * meint der Name die redaktionelle Verantwortung nach § 18 MStV, also
     * [VERANTWORTLICH_NAME] statt [BETREIBER_NAME].
     */
    private const FRAGMENT_RESPONSIBLE_HEAD = '(?P<head><h(?P<level>[1-6])\b[^>]*>[^<]*Redaktionell\s+verantwortlich[^<]*<\/h(?P=level)>\s*<p>[^\S\n]*\n?)';

    /**
     * Abschnitt "Vertreten durch" — nur mit dem bekannten festen Namen.
     */
    private const PATTERN_REPRESENTATIVE = '/\s*<h2>\s*Vertreten\s+durch\s*<\/h2>\s*<p>\s*<strong>\s*Rolland\s+Szalai\s*<\/strong>\s*(?:<br\s*\/?>)?\s*<\/p>/iu';

    /**
     * Erkennungsmerkmal fuer eine abweichende, handgepflegte Fassung.
     */
    private const NEEDLE = 'szalai';

    /** @var array<string, int> */
    private array $counts = [];

    public function handle(TenantBrandingService $branding): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Trockenlauf — es wird nichts geschrieben.');
            $this->newLine();
        }

        $manual = [];
        $missingAddress = [];

        foreach (Tenant::all() as $tenant) {
            $name = (string) $tenant->getAttribute('name');
            $changed = false;
            $needsManual = false;
            $lines = [];

            foreach ([
                'Impressum' => TenantConfigConstants::IMPRESSUM,
                'Datenschutz' => TenantConfigConstants::DATENSCHUTZ,
            ] as $label => $key) {
                $raw = $tenant->getAttribute($key);

                if (! is_string($raw) || trim($raw) === '') {
                    $this->count("übersprungen — kein Text ({$label})");
                    $lines[] = "{$label}: kein Text";

                    continue;
                }

                [$text, $result, $manualHere] = $this->depersonalize($raw);

                $this->count("{$label}: {$result}");
                $lines[] = "{$label}: {$result}";
                $needsManual = $needsManual || $manualHere;

                if ($text === $raw) {
                    continue;
                }

                $changed = true;

                if (! $dryRun) {
                    $branding->set($tenant, $key, $text);
                }
            }

            $this->line("  {$name}: ".implode(', ', $lines));

            if ($needsManual) {
                $manual[] = $name;
            }

            if ($changed && ! $this->hasAddress($tenant)) {
                $missingAddress[] = $name;
            }
        }

        $this->newLine();
        $this->info('Zusammenfassung');
        ksort($this->counts);
        foreach ($this->counts as $label => $count) {
            $this->line("  {$label}: {$count}");
        }

        if ($manual !== []) {
            $this->newLine();
            $this->warn('Abweichende Fassung — nichts geändert, bitte von Hand prüfen:');
            foreach (array_unique($manual) as $name) {
                $this->line("  - {$name}");
            }
        }

        if ($missingAddress !== []) {
            $this->newLine();
            $this->warn('Adressdatensatz leer — die Platzhalter [BETREIBER_STRASSE]/[BETREIBER_PLZ]/[BETREIBER_ORT] rendern als Leerzeile (siehe #49):');
            foreach (array_unique($missingAddress) as $name) {
                $this->line("  - {$name}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string, 2: bool} Rohtext, Befund, "von Hand prüfen"
     */
    private function depersonalize(string $text): array
    {
        if (! str_contains($this->normalize($text), self::NEEDLE)) {
            return [$text, 'keine festen Angaben', false];
        }

        $result = [];

        $withoutRepresentative = (string) preg_replace(self::PATTERN_REPRESENTATIVE, '', $text, 1, $countRepresentative);
        if ($countRepresentative > 0) {
            $text = $withoutRepresentative;
            $result[] = '"Vertreten durch" entfernt';
        }

        $withResponsible = (string) preg_replace_callback(
            '/'.self::FRAGMENT_RESPONSIBLE_HEAD.self::FRAGMENT_ADDRESS.'/iu',
            fn (array $m): string => $m['head'].$this->placeholderBlock($m['indent'], '[VERANTWORTLICH_NAME]'),
            $text,
            -1,
            $countResponsible
        );
        if ($countResponsible > 0) {
            $text = $withResponsible;
            $result[] = 'Adressblock "Redaktionell verantwortlich" ersetzt';
        }

        $replaced = (string) preg_replace_callback(
            '/'.self::FRAGMENT_ADDRESS.'/iu',
            fn (array $m): string => $this->placeholderBlock($m['indent'], '[BETREIBER_NAME]'),
            $text,
            -1,
            $countAddress
        );
        if ($countAddress > 0) {
            $text = $replaced;
            $result[] = 'Adressblock ersetzt ('.$countAddress.'x)';
        }

        // Rest des festen Namens im Text: der Block weicht ab, hier wird nicht
        // geraten.
        if (str_contains($this->normalize($text), self::NEEDLE)) {
            return [$text, ($result === [] ? '' : implode(' + ', $result).', ').'Rest von Hand prüfen', true];
        }

        return [$text, implode(' + ', $result), false];
    }

    private function placeholderBlock(string $indent, string $namePlaceholder): string
    {
        return $indent.'<strong>'.$namePlaceholder.'</strong><br />'."\n"
            .$indent.'[BETREIBER_STRASSE]<br />'."\n"
            .$indent.'[BETREIBER_PLZ] [BETREIBER_ORT]<br />';
    }

    private function hasAddress(Tenant $tenant): bool
    {
        $address = $tenant->address()->first();

        return $address !== null
            && trim((string) $address->getAttribute('address_line_1')) !== ''
            && trim((string) $address->getAttribute('zip')) !== ''
            && trim((string) $address->getAttribute('city')) !== '';
    }

    private function count(string $label): void
    {
        $this->counts[$label] = ($this->counts[$label] ?? 0) + 1;
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
