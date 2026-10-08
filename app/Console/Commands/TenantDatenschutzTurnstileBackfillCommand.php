<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Zieht den Datenschutz-Abschnitt "Bot-Schutz mit Cloudflare Turnstile" (#11)
 * bei Bestandstenants nach.
 *
 * Arbeitet wie tenants:datenschutz:exclusive-leads-backfill (#22) auf dem
 * Rohtext unter branding.datenschutz und schreibt ueber
 * TenantBrandingService::set(). Der Block wird als Unterabschnitt von
 * "Hosting und technische Bereitstellung" eingefuegt (vor der naechsten h2),
 * die Nummern der h2 bleiben unveraendert — damit muss kein Bestandstext
 * umnummeriert werden.
 *
 * Trockenlauf ist Vorgabe, geschrieben wird nur mit --write.
 *
 * Tenants ohne Datenschutztext werden nicht befuellt, nur gemeldet. Weicht die
 * Gliederung ab oder hat der Abschnitt schon Unterabschnitte, wird der Tenant
 * gemeldet statt angefasst.
 *
 * Der Wortlaut ist wortgleich mit TenantLegalDefaults::DATENSCHUTZ
 * (Abschnitt 3.1) — Textaenderungen immer in beiden Dateien.
 */
class TenantDatenschutzTurnstileBackfillCommand extends Command
{
    protected $signature = 'tenants:datenschutz:turnstile-backfill
        {--write : Aenderungen speichern (ohne nur Trockenlauf)}';

    protected $description = 'Ergaenzt bei Bestandstenants den Datenschutz-Abschnitt zu Cloudflare Turnstile (#11)';

    /**
     * Wortlaut wie in TenantLegalDefaults::DATENSCHUTZ (Abschnitt 3.1).
     * :nummer wird durch die Nummer des Abschnitts "Hosting und technische
     * Bereitstellung" ersetzt.
     */
    private const BLOCK = <<<'HTML'
<h3>:nummer.1 Bot-Schutz mit Cloudflare Turnstile</h3>
<p>
    Formulare auf <strong>[PORTAL_NAME]</strong> — insbesondere die Registrierung eines
    Nutzerkontos und die Eintragung eines Betriebs — sind mit „Cloudflare Turnstile“ gegen
    automatisierte Zugriffe (Bots) geschützt. Anbieter ist die Cloudflare Germany GmbH,
    Rosental 7, 80331 München, als Teil der Cloudflare, Inc., 101 Townsend Street,
    San Francisco, CA 94107, USA.
</p>
<p>
    Beim Aufruf eines geschützten Formulars wird ein Skript von Cloudflare geladen. Dabei werden
    folgende Daten an Cloudflare übermittelt und dort ausgewertet, um zu unterscheiden, ob die
    Eingabe von einem Menschen oder von einem Programm stammt:
</p>
<ul>
    <li>IP-Adresse</li>
    <li>Browser- und Geräteangaben (unter anderem Browsertyp und -version, Betriebssystem, Spracheinstellung, Bildschirmauflösung)</li>
    <li>Adresse der aufgerufenen Seite und Referrer-URL</li>
    <li>Interaktionsdaten im Bereich des Formulars (zum Beispiel Maus-, Tastatur- und Touch-Ereignisse)</li>
    <li>Ein von Cloudflare erzeugtes, kurzlebiges Prüf-Token, das wir nach dem Absenden einmalig bei Cloudflare bestätigen lassen</li>
</ul>
<p>
    Turnstile setzt nach Angaben des Anbieters keine Cookies zur Wiedererkennung oder
    Profilbildung und nutzt die Daten nicht für Werbung. Wir erfahren von Cloudflare nur, ob eine
    Prüfung bestanden wurde; die Inhalte Ihrer Formulareingaben werden nicht an Cloudflare
    übermittelt.
</p>
<p>
    <strong>Zweck:</strong> Abwehr automatisierter Massenregistrierungen, von Spam-Einträgen und
    sonstigem Missbrauch unserer Formulare.<br />
    <strong>Rechtsgrundlage:</strong> Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an der
    Sicherheit und Funktionsfähigkeit des Portals und am Schutz vor Missbrauch). Eine Einwilligung
    ist nicht erforderlich, weil der Bot-Schutz technisch notwendig ist und keine Cookies zur
    Wiedererkennung einsetzt.<br />
    <strong>Empfänger:</strong> Cloudflare Germany GmbH und Cloudflare, Inc.<br />
    <strong>Drittlandtransfer:</strong> Eine Verarbeitung in den USA ist nicht ausgeschlossen.
    Cloudflare, Inc. ist unter dem EU-US Data Privacy Framework zertifiziert; ergänzend bestehen
    Standardvertragsklauseln nach Art. 46 Abs. 2 lit. c DSGVO.<br />
    <strong>Speicherdauer:</strong> Das Prüf-Token ist nur wenige Minuten gültig und wird von uns
    nicht gespeichert. Zu jeder Prüfung halten wir Ergebnis, Zeitpunkt, betroffenes Formular sowie
    einen nicht umkehrbaren Prüfwert (Hash) von IP-Adresse und E-Mail-Adresse für höchstens
    90 Tage fest, um Missbrauch erkennen zu können.<br />
    <strong>Weitere Informationen:</strong>
    <a href="https://www.cloudflare.com/privacypolicy/" target="_blank" rel="noreferrer noopener">cloudflare.com/privacypolicy</a>
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
        if (str_contains($this->normalize($text), 'bot-schutz mit cloudflare turnstile')) {
            return [$text, 'vorhanden'];
        }

        $section = '/<h2\b[^>]*>\s*(\d+)\.\s*Hosting\s+und\s+technische\s+Bereitstellung\s*<\/h2>/iu';

        if (preg_match($section, $text, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return [$text, 'unklar — Abschnitt „Hosting und technische Bereitstellung“ nicht gefunden'];
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
