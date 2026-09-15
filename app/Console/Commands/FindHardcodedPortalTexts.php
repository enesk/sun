<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Sucht sichtbare Texte in Portal-Views, die nicht ueber __() laufen (#16).
 *
 * Bewusst eine Regex-Heuristik, kein Blade-Parser:
 *
 * 1. Blade-Kommentare, <script>, <style>, @php-Bloecke, @verbatim und
 *    <?php ?> werden ausgeblendet.
 * 2. Feste Werte in den Attributen aus config('portal-texts.attributes')
 *    sind Treffer; gebundene Attribute (:title, x-bind:title) nicht.
 * 3. Danach werden {{ }}, {!! !!}, @-Direktiven samt Klammerinhalt und
 *    HTML-Tags ausgeblendet; was an Text mit Buchstaben uebrig bleibt, ist
 *    ein Treffer.
 *
 * Ausgeblendetes wird durch Leerzeichen ersetzt, Zeilenumbrueche bleiben,
 * damit die Zeilennummern stimmen. Exit-Code 1 bei mindestens einem Treffer.
 */
class FindHardcodedPortalTexts extends Command
{
    protected $signature = 'portal:find-hardcoded-texts
        {--path=* : Statt der konfigurierten Verzeichnisse nur diese pruefen (relativ zu base_path())}';

    protected $description = 'Findet feste, nicht uebersetzte Texte in den Portal-Views';

    private const TYPE_TEXT = 'Text';

    public function handle(): int
    {
        $files = $this->files();

        if ($files === []) {
            $this->warn('Keine Blade-Views in den konfigurierten Verzeichnissen gefunden.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($files as $relative => $absolute) {
            foreach ($this->scan((string) File::get($absolute)) as [$line, $type, $text]) {
                $rows[] = [$relative, $line, $type, mb_strimwidth($text, 0, 80, '…')];
            }
        }

        if ($rows === []) {
            $this->info(count($files).' Views geprueft, keine festen Texte gefunden.');

            return self::SUCCESS;
        }

        $this->table(['Datei', 'Zeile', 'Fundstelle', 'Text'], $rows);
        $this->newLine();
        $this->error(count($rows).' feste Texte in '.count(array_unique(array_column($rows, 0))).' von '.count($files).' Views.');
        $this->line('Text ueber __() fuehren oder als Ausnahme in config/portal-texts.php eintragen.');

        return self::FAILURE;
    }

    /**
     * @return array<string, string> relativer Pfad => absoluter Pfad
     */
    private function files(): array
    {
        $paths = $this->option('path') ?: (array) config('portal-texts.paths', []);
        $ignored = array_map($this->normalizePath(...), (array) config('portal-texts.ignore_files', []));
        $files = [];

        foreach ($paths as $path) {
            $absolute = base_path($this->normalizePath((string) $path));

            if (File::isFile($absolute)) {
                $files[$this->normalizePath((string) $path)] = $absolute;

                continue;
            }

            if (! File::isDirectory($absolute)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (File::allFiles($absolute) as $file) {
                if (! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $relative = $this->normalizePath(ltrim(str_replace(base_path(), '', $file->getPathname()), '/'));

                if (in_array($relative, $ignored, true)) {
                    continue;
                }

                $files[$relative] = $file->getPathname();
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * @return list<array{int, string, string}> Zeile, Art, Text
     */
    private function scan(string $source): array
    {
        $masked = $this->mask($source, [
            '/\{\{--.*?--\}\}/s',
            '/<!--.*?-->/s',
            '/@verbatim\b.*?@endverbatim\b/s',
            '/@php\b(?!\s*\().*?@endphp\b/s',
            '/<\?php.*?\?>/s',
            '/<script\b.*?<\/script\s*>/is',
            '/<style\b.*?<\/style\s*>/is',
        ]);

        $findings = $this->attributeFindings($masked);

        $masked = $this->mask($masked, [
            '/\{!!.*?!!\}/s',
            '/@?\{\{.*?\}\}/s',
            // @-Direktive mit optionaler, beliebig verschachtelter Klammer; @@ ist ein Escape.
            '/(?<![@\w.])@\w+(?:\s*(\((?:[^()\'"]++|\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|(?1))*\)))?/s',
            '/<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/s',
            '/&#?\w+;/',
        ]);

        foreach (preg_split('/\R/', $masked) ?: [] as $index => $line) {
            foreach (preg_split('/\s{2,}/', trim($line)) ?: [] as $chunk) {
                $text = trim($chunk);

                if ($text !== '' && preg_match('/\p{L}/u', $text) && ! $this->isAllowed($text)) {
                    $findings[] = [$index + 1, self::TYPE_TEXT, $text];
                }
            }
        }

        usort($findings, fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $findings;
    }

    /**
     * @return list<array{int, string, string}>
     */
    private function attributeFindings(string $masked): array
    {
        $attributes = implode('|', array_map(
            fn (string $name): string => preg_quote($name, '/'),
            (array) config('portal-texts.attributes', []),
        ));

        if ($attributes === '') {
            return [];
        }

        preg_match_all(
            '/(?<![\w:.@-])('.$attributes.')\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i',
            $masked,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE,
        );

        $findings = [];

        foreach ($matches as $match) {
            $value = $match[2][1] >= 0 ? $match[2][0] : ($match[3][0] ?? '');
            $literal = trim((string) preg_replace(['/\{!!.*?!!\}/s', '/\{\{.*?\}\}/s', '/&#?\w+;/'], ' ', $value));

            if (! preg_match('/\p{L}/u', $literal) || $this->isAllowed($literal)) {
                continue;
            }

            $line = substr_count(substr($masked, 0, $match[0][1]), "\n") + 1;
            $findings[] = [$line, $match[1][0], $literal];
        }

        return $findings;
    }

    /**
     * Ersetzt jeden Treffer durch Leerzeichen und behaelt die Zeilenumbrueche.
     *
     * @param  list<string>  $patterns
     */
    private function mask(string $source, array $patterns): string
    {
        foreach ($patterns as $pattern) {
            $source = (string) preg_replace_callback(
                $pattern,
                fn (array $match): string => (string) preg_replace('/[^\n]/', ' ', $match[0]),
                $source,
            );
        }

        return $source;
    }

    private function isAllowed(string $text): bool
    {
        if (in_array($text, (array) config('portal-texts.allow', []), true)) {
            return true;
        }

        foreach ((array) config('portal-texts.allow_patterns', []) as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }

        return false;
    }

    private function normalizePath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), '/');
    }
}
