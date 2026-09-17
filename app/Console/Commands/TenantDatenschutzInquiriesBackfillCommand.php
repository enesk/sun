<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Zieht den Datenschutz-Abschnitt "Anfragen über den Anfrage-Dialog" (#35)
 * bei Bestandstenants nach.
 *
 * Arbeitet wie tenants:impressum:backfill auf dem Rohtext unter
 * branding.datenschutz (Platzhalter bleiben stehen) und schreibt ueber
 * TenantBrandingService::set(). Anders als dort wird nicht angehaengt,
 * sondern direkt nach "9. Kontaktaufnahme" eingefuegt; die folgenden
 * nummerierten Ueberschriften ruecken um eins weiter, der "Stand" wird
 * auf den Monat der Aenderung gesetzt. Das geht nur, weil alle Portale die
 * Gliederung der Vorlage TenantLegalDefaults::DATENSCHUTZ tragen — fehlt
 * die Ueberschrift "9. Kontaktaufnahme", wird der Tenant nur gemeldet.
 *
 * Tenants ohne Datenschutztext werden nicht befuellt, nur gemeldet.
 */
class TenantDatenschutzInquiriesBackfillCommand extends Command
{
    protected $signature = 'tenants:datenschutz:inquiries-backfill
        {--dry-run : Nur melden, nichts schreiben}';

    protected $description = 'Ergaenzt bei Bestandstenants den Datenschutz-Abschnitt zu Anfragen ueber den Anfrage-Dialog (#35)';

    /**
     * Wortlaut aus "Vorschlag: Datenschutztext Anfragen (#35)", von Enes freigegeben.
     * :nummer wird durch die Abschnittsnummer ersetzt.
     */
    private const BLOCK = <<<'HTML'
<h2>:nummer. Anfragen über den Anfrage-Dialog</h2>
<p>
    Über den Anfrage-Dialog auf einem Firmenprofil können Sie eine unverbindliche Anfrage an
    den jeweiligen Betrieb stellen. Dabei verarbeiten wir die Angaben, die Sie im Dialog
    machen, zum Beispiel:
</p>
<ul>
    <li>Ihre Antworten auf die Fragen des Anfrage-Dialogs</li>
    <li>Name, Telefonnummer und/oder E-Mail-Adresse, sofern Sie diese angeben</li>
</ul>
<p>
    Ihre Anfrage samt dieser Angaben wird an den von Ihnen ausgewählten Betrieb weitergegeben
    und dort angezeigt. Das gilt auch dann, wenn der Betrieb sein Firmenprofil zum Zeitpunkt
    Ihrer Anfrage noch nicht selbst verwaltet (übernommen) hat — übernimmt er es später, sieht
    er auch zuvor eingegangene Anfragen samt Ihrer Kontaktdaten.
</p>
<p>
    <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Anbahnung eines Vertrags mit
    dem angefragten Betrieb), für freiwillige Angaben, die über die Anfrage hinausgehen,
    Art. 6 Abs. 1 lit. a DSGVO (Einwilligung).<br />
    <strong>Empfänger:</strong> Der von Ihnen ausgewählte Betrieb.<br />
    <strong>Speicherdauer:</strong> Ihre Anfrage wird derzeit ohne festgelegte Löschfrist
    gespeichert, damit sie dem Betrieb auch nach einer späteren Profilübernahme noch vorliegt.
    Sie können die Löschung jederzeit verlangen (siehe „Ihre Rechte“); wenden Sie sich dazu an
    <a href="mailto:[BETREIBER_EMAIL]">[BETREIBER_EMAIL]</a>.
</p>
HTML;

    private const MONTHS = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    public function handle(TenantBrandingService $branding): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->comment('Trockenlauf — es wird nichts geschrieben.');
            $this->newLine();
        }

        $counts = [];
        $manual = [];

        foreach (Tenant::all() as $tenant) {
            $name = (string) $tenant->name;
            $raw = $tenant->getAttribute(TenantConfigConstants::DATENSCHUTZ);

            if (! is_string($raw) || trim($raw) === '') {
                $result = 'übersprungen — kein Datenschutztext';
                $manual[] = "{$name} ({$result})";
            } else {
                [$text, $result] = $this->apply($raw);

                if ($text !== $raw && ! $dryRun) {
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
        if (str_contains($this->normalize($text), 'anfrage-dialog')) {
            return [$text, 'vorhanden'];
        }

        $contact = '/<h2\b[^>]*>\s*(\d+)\.\s*Kontaktaufnahme\s*<\/h2>/iu';

        if (preg_match($contact, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [$text, 'unklar — Abschnitt „Kontaktaufnahme“ nicht gefunden'];
        }

        $number = (int) $match[1][0] + 1;
        $afterContact = $match[0][1] + strlen($match[0][0]);

        // Einfuegen vor der naechsten h2 nach "Kontaktaufnahme", sonst ans Ende
        $next = preg_match('/<h2\b/i', $text, $nextMatch, PREG_OFFSET_CAPTURE, $afterContact) === 1
            ? $nextMatch[0][1]
            : strlen($text);

        $head = substr($text, 0, $next);
        $tail = substr($text, $next);

        // Folgende nummerierte Ueberschriften ruecken um eins weiter
        $tail = (string) preg_replace_callback(
            '/(<h2\b[^>]*>\s*)(\d+)(\.)/iu',
            fn (array $m): string => $m[1].((int) $m[2] + 1).$m[3],
            $tail,
        );

        $block = str_replace(':nummer', (string) $number, self::BLOCK);
        $indent = preg_match('/([ \t]*)$/', substr($head, 0, $next), $ws) === 1 ? $ws[1] : '';
        $block = $this->indent($block, $indent);

        $text = rtrim($head)."\n\n".$indent.ltrim($block)."\n\n".$indent.ltrim($tail);

        return [$this->updateStand($text), 'eingefügt als Abschnitt '.$number];
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

    private function indent(string $block, string $indent): string
    {
        if ($indent === '') {
            return $block;
        }

        return implode("\n", array_map(fn (string $line): string => $line === '' ? '' : $indent.$line, explode("\n", $block)));
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
