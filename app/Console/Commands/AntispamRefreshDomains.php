<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\AntiSpam\DisposableDomainList;
use App\AntiSpam\Support\AntiSpamConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Pflegt resources/antispam/disposable-domains.txt (#8).
 *
 *   php artisan antispam:refresh-domains            # holt die Quelle, schreibt die Datei
 *   php artisan antispam:refresh-domains --dry-run  # zeigt nur, was sich aendern wuerde
 *   php artisan antispam:refresh-domains --limit=500
 *
 * Die Datei hat zwei Bloecke, und nur der zweite wird angefasst:
 *
 *   "Dauerhaft im Repo"  — von Hand gepflegt, bleibt IMMER unveraendert. Dort
 *                          stehen die R6-Domains aus docs/turnstile.md §7 und
 *                          alles, was wir selbst gesehen haben; eine fremde
 *                          Liste kann das nicht wissen.
 *   "Aus der Quelle"     — wird komplett neu geschrieben.
 *
 * Erkannt werden die Bloecke an {@see self::KEEP_MARKER} und
 * {@see self::SOURCE_MARKER}. Fehlt die Quell-Markierung, bricht der Command
 * ab, statt zu raten — eine Sperrliste ist der falsche Ort fuer Heuristik.
 *
 * `--limit` ist nur fuer einen Blick auf die Quelle gedacht und schneidet
 * alphabetisch ab; die Vorgabe ist 0, also alles. Die Quelle hat ueber 9.000
 * Domains, und die Bot-Analyse in #2 hat in 30 Tagen NULL Treffer darauf
 * gefunden (docs/turnstile.md §1.2) — wer sie uebernimmt, tauscht ein
 * ungemessenes Risiko gegen ein neues: jede Domain ist ein moegliches falsches
 * Positiv gegen einen echten Interessenten.
 *
 * Der Command schreibt eine Datei im Repo, laeuft also nicht im Cron, sondern
 * von Hand; die Aenderung geht durch ein Code-Review wie jede andere.
 */
class AntispamRefreshDomains extends Command
{
    /** Beginn des handgepflegten Teils. Nicht aendern. */
    public const KEEP_MARKER = '# --- Dauerhaft im Repo';

    /** Beginn des Teils, den dieser Command neu schreibt. Nicht aendern. */
    public const SOURCE_MARKER = '# --- Aus der Quelle';

    protected $signature = 'antispam:refresh-domains
        {--dry-run : Nur anzeigen, nichts schreiben}
        {--limit=0 : Hoechstzahl Domains aus der Quelle, alphabetisch; 0 = alle}';

    protected $description = 'Holt die Wegwerf-E-Mail-Sperrliste und schreibt resources/antispam/disposable-domains.txt neu';

    public function handle(): int
    {
        $url = (string) AntiSpamConfig::get('disposable.refresh_url', '');

        if ($url === '') {
            $this->components->error('antispam.disposable.refresh_url ist nicht gesetzt.');

            return self::FAILURE;
        }

        $pfad = app(DisposableDomainList::class)->path();

        if (! is_file($pfad)) {
            $this->components->error("Sperrliste nicht gefunden: {$pfad}");

            return self::FAILURE;
        }

        $this->components->info("Quelle: {$url}");

        try {
            $antwort = Http::timeout(20)->get($url);
        } catch (Throwable $exception) {
            $this->components->error('Quelle nicht erreichbar: '.$exception->getMessage());

            return self::FAILURE;
        }

        if (! $antwort->successful()) {
            $this->components->error("Quelle antwortet mit HTTP {$antwort->status()}.");

            return self::FAILURE;
        }

        $neu = $this->domainsFrom($antwort->body());

        if ($neu === []) {
            $this->components->error('Die Quelle enthaelt keine verwertbaren Domains — Datei bleibt unberuehrt.');

            return self::FAILURE;
        }

        sort($neu);
        $limit = (int) $this->option('limit');

        if ($limit > 0 && count($neu) > $limit) {
            $this->components->warn(sprintf(
                'Quelle hat %d Domains, uebernommen werden die ersten %d (--limit, alphabetisch).',
                count($neu),
                $limit,
            ));
            $neu = array_slice($neu, 0, $limit);
        }

        $bloecke = $this->blocks($pfad);

        if ($bloecke === null) {
            $this->components->error(sprintf(
                'In %s fehlt eine der Markierungen "%s" / "%s" — abgebrochen, damit nichts von Hand Gepflegtes verloren geht.',
                basename($pfad),
                self::KEEP_MARKER,
                self::SOURCE_MARKER,
            ));

            return self::FAILURE;
        }

        $alt = app(DisposableDomainList::class)->patterns();
        $behalten = $this->domainsFrom(implode("\n", $bloecke['keep']));

        $inhalt = $this->render($url, $neu, $bloecke['keep']);

        $dazu = array_values(array_diff($neu, $alt));
        $weg = array_values(array_diff($alt, $neu, $behalten));

        $this->components->twoColumnDetail('Domains aus der Quelle', (string) count($neu));
        $this->components->twoColumnDetail('Dauerhaft im Repo', (string) count($behalten));
        $this->components->twoColumnDetail('Neu', (string) count($dazu));
        $this->components->twoColumnDetail('Entfallen', (string) count($weg));

        if ($dazu !== []) {
            $this->line('  + '.implode(', ', array_slice($dazu, 0, 15)).(count($dazu) > 15 ? ' …' : ''));
        }

        if ($weg !== []) {
            $this->line('  - '.implode(', ', array_slice($weg, 0, 15)).(count($weg) > 15 ? ' …' : ''));
        }

        if ($this->option('dry-run')) {
            $this->components->info('--dry-run: nichts geschrieben.');

            return self::SUCCESS;
        }

        file_put_contents($pfad, $inhalt);
        DisposableDomainList::flush();

        $this->components->info("Geschrieben: {$pfad}");

        return self::SUCCESS;
    }

    /**
     * Der handgepflegte Block einschliesslich seiner Kommentare — die
     * Begruendung, warum eine Domain drinsteht, ist so wertvoll wie die Domain.
     *
     * Null heisst: eine der beiden Markierungen fehlt. Dann wird nicht
     * geschrieben.
     *
     * @return array{keep: list<string>}|null
     */
    private function blocks(string $pfad): ?array
    {
        $zeilen = file($pfad, FILE_IGNORE_NEW_LINES) ?: [];
        $keep = [];
        $inKeep = false;
        $quelleGesehen = false;

        foreach ($zeilen as $zeile) {
            $getrimmt = trim($zeile);

            if (str_starts_with($getrimmt, self::SOURCE_MARKER)) {
                $quelleGesehen = true;

                break;
            }

            if (str_starts_with($getrimmt, self::KEEP_MARKER)) {
                $inKeep = true;
            }

            if ($inKeep) {
                $keep[] = rtrim($zeile);
            }
        }

        if (! $inKeep || ! $quelleGesehen) {
            return null;
        }

        return ['keep' => $keep];
    }

    /**
     * @param  list<string>  $domains
     * @param  list<string>  $behalten
     */
    private function render(string $url, array $domains, array $behalten): string
    {
        $kopf = <<<TXT
        # Wegwerf-E-Mail-Domains — Sperrliste fuer die Registrierung (#8)
        #
        # Verwendet von App\\AntiSpam\\DisposableDomainList, geprueft in
        # App\\AntiSpam\\Rules\\NotDisposableEmailRule.
        #
        # Stand:   {$this->heute()}
        # Pflege:  php artisan antispam:refresh-domains
        # Quelle:  {$url}
        #
        # DER BLOCK "AUS DER QUELLE" WIRD VOM COMMAND NEU GESCHRIEBEN. Eigene
        # Domains gehoeren unter "Dauerhaft im Repo" — nur dieser Block
        # ueberlebt den naechsten Lauf unveraendert.
        #
        # Format: eine Domain je Zeile, Kleinschreibung. Leerzeilen und Zeilen mit
        # `#` werden uebersprungen. `*` ist ein Platzhalter fuer genau ein Label:
        #   tempmail.*     trifft tempmail.de, tempmail.net, nicht a.tempmail.de
        #   *.tempmail.com trifft a.tempmail.com, nicht tempmail.com
        # Subdomains einer gelisteten Domain sind immer mit gesperrt.

        TXT;

        $teile = [rtrim($kopf), ''];

        if ($behalten !== []) {
            $teile = [...$teile, ...$behalten, ''];
        }

        $teile = [...$teile, self::SOURCE_MARKER.' (wird von antispam:refresh-domains neu geschrieben) ---', '', ...$domains];

        return implode("\n", $teile)."\n";
    }

    private function heute(): string
    {
        return now()->format('d.m.Y');
    }

    /**
     * Rohtext in saubere Domainmuster. Verworfen wird alles, was keine Domain
     * sein kann — eine kaputte Quelle darf die Datei nicht verunstalten.
     *
     * @return list<string>
     */
    private function domainsFrom(string $body): array
    {
        $domains = [];

        foreach (preg_split('/\R/', $body) ?: [] as $zeile) {
            $zeile = Str::lower(trim((string) $zeile));

            if ($zeile === '' || str_starts_with($zeile, '#')) {
                continue;
            }

            if (preg_match('/^[a-z0-9*][a-z0-9.*_-]*\.[a-z0-9*][a-z0-9*-]*$/', $zeile) !== 1) {
                continue;
            }

            $domains[$zeile] = true;
        }

        return array_keys($domains);
    }
}
