<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Zieht den Unterabschnitt "Direktanfragen an Betriebe mit Premium-Eintrag"
 * (#22, exklusive Anfragen aus #9) bei Bestandstenants nach.
 *
 * Arbeitet wie tenants:datenschutz:inquiries-backfill (#35) auf dem Rohtext
 * unter branding.datenschutz und schreibt ueber TenantBrandingService::set().
 * Der Block wird als letzter Teil des Abschnitts "Anfragen über den
 * Anfrage-Dialog" eingefuegt (vor der naechsten h2), die Nummern der h2
 * bleiben unveraendert. Fehlt der Abschnitt aus #35, wird der Tenant nur
 * gemeldet — dann zuerst tenants:datenschutz:inquiries-backfill laufen lassen.
 *
 * Trockenlauf ist Vorgabe, geschrieben wird nur mit --write. Der Wortlaut
 * muss vor dem Schreiben rechtlich freigegeben sein (#22).
 *
 * Tenants ohne Datenschutztext werden nicht befuellt, nur gemeldet.
 */
class TenantDatenschutzExclusiveLeadsBackfillCommand extends Command
{
    protected $signature = 'tenants:datenschutz:exclusive-leads-backfill
        {--write : Aenderungen speichern (ohne nur Trockenlauf)}';

    protected $description = 'Ergaenzt bei Bestandstenants den Datenschutz-Abschnitt zu Direktanfragen an Premium-Betriebe (#22)';

    /**
     * Wortlaut wie in TenantLegalDefaults::DATENSCHUTZ (Abschnitt 10.1).
     * :nummer wird durch die Nummer des Abschnitts "Anfragen über den
     * Anfrage-Dialog" ersetzt.
     */
    private const BLOCK = <<<'HTML'
<h3>:nummer.1 Direktanfragen an Betriebe mit Premium-Eintrag</h3>
<p>
    Betriebe mit einem kostenpflichtigen Premium-Eintrag können Anfragen direkt über
    <strong>[PORTAL_NAME]</strong> erhalten. Stellen Sie über das Firmenprofil eines solchen
    Betriebs eine Anfrage, gilt abweichend von den Angaben oben:
</p>
<ul>
    <li>Ihre Anfrage wird ausschließlich an diesen einen Betrieb übermittelt und nicht an weitere Betriebe oder sonstige Dritte weitergegeben.</li>
    <li>Ihre Anfrage wird bei uns im Portal gespeichert. Der Betrieb wird per E-Mail über die Anfrage informiert und kann sie in seinem Betriebsbereich abrufen.</li>
</ul>
<p>
    <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. b DSGVO (Anbahnung eines Vertrags mit
    dem angefragten Betrieb).<br />
    <strong>Empfänger:</strong> Ausschließlich der von Ihnen angefragte Betrieb.<br />
    <strong>Speicherdauer:</strong> Ihre Kontaktdaten (Name, E-Mail-Adresse, Telefonnummer) und
    Ihre frei formulierten Angaben werden 12 Monate nach Eingang der Anfrage automatisch
    gelöscht. Danach bleiben nur Ihre ausgewählten Antworten ohne Kontaktdaten gespeichert,
    damit Anfragekontingent und Anfragestatistik des Betriebs nachvollziehbar bleiben. Eine
    frühere Löschung können Sie jederzeit verlangen (siehe „Ihre Rechte“); wenden Sie sich dazu an
    <a href="mailto:[BETREIBER_EMAIL]">[BETREIBER_EMAIL]</a>.
</p>
HTML;

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
            $name = (string) $tenant->name;
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
        if (str_contains($this->normalize($text), 'direktanfragen an betriebe mit premium-eintrag')) {
            return [$text, 'vorhanden'];
        }

        $section = '/<h2\b[^>]*>\s*(\d+)\.\s*Anfragen\s+über\s+den\s+Anfrage-Dialog\s*<\/h2>/iu';

        if (preg_match($section, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [$text, 'unklar — Abschnitt „Anfragen über den Anfrage-Dialog“ nicht gefunden'];
        }

        $number = (int) $match[1][0];
        $afterSection = $match[0][1] + strlen($match[0][0]);

        // Einfuegen vor der naechsten h2 nach dem Abschnitt, sonst ans Ende
        $next = preg_match('/<h2\b/i', $text, $nextMatch, PREG_OFFSET_CAPTURE, $afterSection) === 1
            ? $nextMatch[0][1]
            : strlen($text);

        $head = substr($text, 0, $next);
        $tail = substr($text, $next);

        // Handgepflegte Unterabschnitte nicht durchnummerieren, lieber melden
        if (preg_match('/<h3\b/i', substr($head, $afterSection)) === 1) {
            return [$text, 'unklar — Abschnitt hat bereits Unterabschnitte'];
        }

        $block = str_replace(':nummer', (string) $number, self::BLOCK);
        $indent = preg_match('/([ \t]*)$/', $head, $ws) === 1 ? $ws[1] : '';
        $block = $this->indent($block, $indent);

        $text = rtrim($head)."\n\n".$indent.ltrim($block)."\n\n".$indent.ltrim($tail);

        return [$this->updateStand($text), "eingefügt als Abschnitt {$number}.1"];
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
