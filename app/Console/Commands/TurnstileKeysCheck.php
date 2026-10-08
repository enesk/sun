<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Turnstile\Config\TurnstileConfigResolver;
use App\Turnstile\Support\SiteverifyProbe;
use Illuminate\Console\Command;

/**
 * Prueft die drei Turnstile-Widget-Gruppen aus docs/turnstile.md Abschnitt 8
 * (#14): liegt je Gruppe ein echtes Schluesselpaar in der .env, und loest jeder
 * eingetragene Hostname auf die richtige Gruppe auf?
 *
 * Damit ist die Abnahme von #14 pruefbar, bevor #5 (Blade-Komponente) und
 * #6/#7 (Formulare) stehen — vorher gibt es im Frontend keine Stelle, die ein
 * Widget einbaut, also auch keinen Sichttest.
 *
 *   php artisan turnstile:keys:check
 *   php artisan turnstile:keys:check --siteverify   # Secret gegen Cloudflare
 *   php artisan turnstile:keys:check --json
 *
 * `--siteverify` schickt je Gruppe einen erfundenen Token an Cloudflare. Die
 * Antwort `invalid-input-response` heisst "Secret gilt, nur der Token nicht";
 * `invalid-input-secret` heisst "falscher Schluessel". So ist jedes Secret
 * geprueft, ohne ein Formular abzuschicken. Ausgegeben wird nie ein Secret,
 * vom Sitekey nur die ersten Zeichen.
 *
 * Rueckgabe 0 nur dann, wenn jede Gruppe ein vollstaendiges Paar hat, keiner
 * der Werte ein Cloudflare-Testschluessel ist und jeder Hostname seine Gruppe
 * findet. Lokal ist ein roter Lauf der Normalfall: dort gelten die
 * Testschluessel.
 */
class TurnstileKeysCheck extends Command
{
    private const OK = 'ok';

    private const TESTKEY = 'Testschluessel';

    private const FEHLT = 'fehlt';

    protected $signature = 'turnstile:keys:check
        {--siteverify : Secret je Gruppe gegen Cloudflare pruefen}
        {--json : Ergebnis als JSON statt als Tabelle}';

    protected $description = 'Prueft Schluessel und Hostnames der Turnstile-Widget-Gruppen (#14)';

    public function handle(): int
    {
        $gruppen = TurnstileConfigResolver::groups();

        if ($gruppen === []) {
            $this->components->error('config/turnstile.php kennt keine Gruppen.');

            return self::FAILURE;
        }

        $zeilen = [];

        foreach ($gruppen as $gruppe => $daten) {
            $gruppe = (string) $gruppe;
            $hostnames = is_array($daten['hostnames'] ?? null) ? $daten['hostnames'] : [];
            $siteKey = trim((string) ($daten['site_key'] ?? ''));
            $secretKey = trim((string) ($daten['secret_key'] ?? ''));

            $zeilen[] = [
                'gruppe' => $gruppe,
                'name' => (string) ($daten['name'] ?? ''),
                'hostnames' => count($hostnames),
                'sitekey' => $this->gekuerzt($siteKey),
                'zustand' => $this->zustand($gruppe),
                'siteverify' => $this->option('siteverify') ? $this->siteverify($secretKey) : '–',
                'hostname_fehler' => $this->falschZugeordnet($gruppe, $hostnames),
            ];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($zeilen, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $this->abschluss($zeilen);
        }

        $this->table(
            ['Gruppe', 'Name', 'Hostnames', 'Sitekey', 'Schluessel', 'Siteverify', 'Falsch zugeordnet'],
            array_map(fn (array $z): array => [
                $z['gruppe'],
                $z['name'],
                (string) $z['hostnames'],
                $z['sitekey'],
                $z['zustand'],
                $z['siteverify'],
                $z['hostname_fehler'] === [] ? '–' : implode(', ', $z['hostname_fehler']),
            ], $zeilen),
        );

        $gesamt = array_sum(array_column($zeilen, 'hostnames'));
        $this->line("Eingetragene Hostnames insgesamt: {$gesamt} (Soll laut docs/turnstile.md Abschnitt 8: 18)");

        // Die Probe laeuft auch gegen Subdomains, die bewusst NICHT im Widget
        // stehen: sie muessen ueber ihren Elterneintrag dieselbe Gruppe finden.
        foreach (['www.elektrikerportal.com' => 'A', 'apotheke.firmenfreund.de' => 'B', 'zahnarzt.firmenfreund.de' => 'B'] as $host => $soll) {
            $ist = TurnstileConfigResolver::groupForHost($host);
            $hinweis = $ist === $soll ? 'ok' : "FALSCH, erwartet {$soll}";
            $this->line("  {$host} → Gruppe {$ist} ({$hinweis})");
        }

        return $this->abschluss($zeilen);
    }

    /**
     * @param  list<array<string, mixed>>  $zeilen
     */
    private function abschluss(array $zeilen): int
    {
        foreach ($zeilen as $zeile) {
            if ($zeile['zustand'] !== self::OK || $zeile['hostname_fehler'] !== []) {
                return self::FAILURE;
            }

            if ($this->option('siteverify') && $zeile['siteverify'] !== self::OK) {
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    private function zustand(string $gruppe): string
    {
        return match (TurnstileConfigResolver::groupKeyState($gruppe)) {
            TurnstileConfigResolver::KEYS_MISSING => self::FEHLT,
            TurnstileConfigResolver::KEYS_TEST => self::TESTKEY,
            default => self::OK,
        };
    }

    /**
     * Hostnames, die nicht auf ihre eigene Gruppe auflösen — etwa weil
     * derselbe Name in zwei Gruppen steht.
     *
     * @param  array<int|string, mixed>  $hostnames
     * @return list<string>
     */
    private function falschZugeordnet(string $gruppe, array $hostnames): array
    {
        $fehler = [];

        foreach ($hostnames as $host) {
            $host = trim((string) $host);

            if ($host === '') {
                continue;
            }

            $ist = TurnstileConfigResolver::groupForHost($host);

            if ($ist !== $gruppe) {
                $fehler[] = "{$host} → {$ist}";
            }
        }

        return $fehler;
    }

    /**
     * Probe gegen Cloudflare. Die Bewertung der Antwort steht in
     * App\Turnstile\Support\SiteverifyProbe — dieselbe Stelle, die das
     * Admin-Panel fuer "Verbindung testen" benutzt (#9).
     */
    private function siteverify(string $secretKey): string
    {
        return SiteverifyProbe::check($secretKey)->label;
    }

    /** Der Sitekey ist oeffentlich, wird hier aber trotzdem nur angedeutet. */
    private function gekuerzt(string $wert): string
    {
        if ($wert === '') {
            return '(leer)';
        }

        return mb_strlen($wert) <= 12 ? $wert : mb_substr($wert, 0, 10).'…';
    }
}
