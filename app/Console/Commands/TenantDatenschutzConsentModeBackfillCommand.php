<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Constants\TenantConfigConstants;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Console\Command;

/**
 * Ergaenzt bei Bestandstenants im Abschnitt "Analyse-Cookies" der
 * Datenschutzerklaerung den Absatz zum Consent Mode (#5).
 *
 * Arbeitet wie tenants:datenschutz:cookie-names-backfill (#30) auf dem
 * Rohtext unter branding.datenschutz und schreibt ueber
 * TenantBrandingService::set(). Trockenlauf ist Vorgabe, geschrieben wird
 * nur mit --write.
 *
 * Angefasst werden nur Tenants mit gesetzter GA4-Mess-ID — ohne GA4 wird
 * kein Consent Mode eingesetzt, der Absatz waere dort unzutreffend.
 * Eingefuegt wird direkt hinter dem Rechtsgrundlage-Absatz des Abschnitts
 * 8.2; wird dieser Anker nicht gefunden, wird der Tenant gemeldet statt
 * angefasst.
 *
 * Der Wortlaut ist wortgleich mit TenantLegalDefaults::DATENSCHUTZ
 * (Abschnitt 8.2) — Textaenderungen immer in beiden Dateien.
 */
class TenantDatenschutzConsentModeBackfillCommand extends Command
{
    protected $signature = 'tenants:datenschutz:consent-mode-backfill
        {--write : Aenderungen speichern (ohne nur Trockenlauf)}';

    protected $description = 'Ergaenzt bei Bestandstenants den Consent-Mode-Absatz im Analyse-Abschnitt der Datenschutzerklaerung (#5)';

    /** Wortlaut wie in TenantLegalDefaults::DATENSCHUTZ (Abschnitt 8.2). */
    private const ABSATZ = <<<'HTML'
    <p>
        <strong>Consent Mode:</strong> Wir setzen Google Analytics im sogenannten Consent Mode ein.
        Solange Sie keine Einwilligung erteilt haben, werden keine Cookies gesetzt und keine
        Nutzerkennungen gespeichert. In diesem Zustand übermittelt der Dienst lediglich pseudonyme,
        cookielose Messwerte an Google (Aufruf einer Seite, Zeitpunkt, gekürzte IP-Adresse,
        Einwilligungsstatus), ohne dass einzelne Nutzer wiedererkannt werden. Google nutzt diese
        Werte, um fehlende Daten statistisch zu modellieren. Rechtsgrundlage ist unser berechtigtes
        Interesse an einer reichweitenbezogenen Auswertung unseres Angebots
        (Art. 6 Abs. 1 lit. f DSGVO). Erst nach Ihrer Einwilligung (Art. 6 Abs. 1 lit. a DSGVO,
        § 25 Abs. 1 TDDDG) werden Cookies gesetzt und das vollständige Tracking aktiviert. Sie
        können Ihre Einwilligung jederzeit über die Cookie-Einstellungen widerrufen.
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
            $name = (string) $tenant->getAttribute('name');
            $result = $this->handleTenant($tenant, $branding, $write);

            if (str_starts_with($result, 'unklar')) {
                $manual[] = "{$name} ({$result})";
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

    private function handleTenant(Tenant $tenant, TenantBrandingService $branding, bool $write): string
    {
        $ga = $branding->get($tenant, TenantConfigConstants::GOOGLE_ANALYTICS_ID);

        if (! is_string($ga) || trim($ga) === '') {
            return 'übersprungen — kein GA4';
        }

        $raw = $tenant->getAttribute(TenantConfigConstants::DATENSCHUTZ);

        if (! is_string($raw) || trim($raw) === '') {
            return 'unklar — kein Datenschutztext';
        }

        [$text, $result] = $this->apply($raw);

        if ($text !== $raw && $write) {
            $branding->set($tenant, TenantConfigConstants::DATENSCHUTZ, $text);
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: string} Rohtext und Befund
     */
    private function apply(string $text): array
    {
        if (str_contains($text, 'Consent Mode')) {
            return [$text, 'vorhanden'];
        }

        $anker = '/<p>\s*<strong>\s*Rechtsgrundlage:\s*<\/strong>\s*Art\. 6 Abs\. 1 lit\. a DSGVO \(Einwilligung\)\.\s*<br\s*\/?>\s*Sie können Ihre Einwilligung jederzeit über den Cookie-Banner widerrufen\.\s*<\/p>/u';

        if (preg_match($anker, $text, $match) !== 1) {
            return [$text, 'unklar — Analyse-Abschnitt nicht wie erwartet'];
        }

        $text = str_replace($match[0], $match[0]."\n".self::ABSATZ, $text);

        return [$this->updateStand($text), 'Consent-Mode-Absatz ergänzt'];
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
}
