<?php

declare(strict_types=1);

namespace App\Csp\Support;

use App\Csp\Dto\CspViolation;

/**
 * Liest einen CSP-Bericht und gibt normalisierte Verstoesse zurueck (#51).
 *
 * Zwei Formate, beide muessen rein, weil sich die Browser nicht einig sind:
 *
 *   application/csp-report    {"csp-report": {"violated-directive": …}}
 *                             Safari und Firefox, Ziel ist `report-uri`.
 *   application/reports+json  [{"type":"csp-violation","body":{…}}, …]
 *                             Chrome seit der Umstellung auf `report-to` /
 *                             `Reporting-Endpoints`; mehrere Berichte je
 *                             Anfrage, und die Schluessel heissen camelCase
 *                             (`blockedURL`, `documentURL`).
 *
 * Darum wird jeder Schluessel in beiden Schreibweisen gesucht. Ein Bericht
 * ohne Direktive ist unbrauchbar und wird verworfen — alles andere ist
 * optional, Safari laesst z. B. `effective-directive` weg.
 *
 * `blocked_uri` ist nicht immer eine URL: der Browser schickt `inline`,
 * `eval`, `data`, `blob` oder `self` als Schluesselwort. Beides wird gleich
 * behandelt, nur URLs verlieren Query und Fragment — sonst zaehlt jede
 * Cache-Busting-Variante derselben Werbe-URL als eigener Verstoss und die
 * Entdopplung greift nicht.
 */
class CspReportPayload
{
    /** Spaltenbreite von blocked_uri/document_uri/source_file. */
    private const URI_MAX = 500;

    /** Spaltenbreite von violated_directive/effective_directive. */
    private const DIRECTIVE_MAX = 64;

    /**
     * Hoechstens so viele Verstoesse je Anfrage. Chrome bundelt, aber ein
     * Absender soll mit einem Aufruf nicht beliebig viele Zeilen erzeugen.
     */
    private const MAX_VIOLATIONS = 20;

    /**
     * @param  mixed  $decoded  Dekodierter JSON-Rumpf der Anfrage
     * @return list<CspViolation>
     */
    public static function violations(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        $violations = [];

        foreach (self::bodies($decoded) as $body) {
            $violation = self::violation($body);

            if ($violation instanceof CspViolation) {
                $violations[] = $violation;
            }

            if (count($violations) >= self::MAX_VIOLATIONS) {
                break;
            }
        }

        return $violations;
    }

    /**
     * Die Rumpfobjekte beider Formate.
     *
     * @param  array<array-key, mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    private static function bodies(array $decoded): array
    {
        // application/csp-report: ein Objekt unter "csp-report".
        if (is_array($decoded['csp-report'] ?? null)) {
            return [$decoded['csp-report']];
        }

        // application/reports+json: Liste von Berichten, nur csp-violation
        // interessiert. Andere Typen (deprecation, intervention) kommen ueber
        // denselben Endpunkt, wenn der Browser sie bundelt.
        $bodies = [];

        foreach ($decoded as $report) {
            if (! is_array($report)) {
                continue;
            }

            $type = $report['type'] ?? 'csp-violation';

            if (is_string($type) && $type !== 'csp-violation') {
                continue;
            }

            if (is_array($report['body'] ?? null)) {
                $bodies[] = $report['body'];

                continue;
            }

            // Manche Fassungen schicken den Rumpf flach.
            $bodies[] = $report;
        }

        return $bodies;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function violation(array $body): ?CspViolation
    {
        $effective = self::directive($body, ['effective-directive', 'effectiveDirective']);
        $violated = self::directive($body, ['violated-directive', 'violatedDirective']) ?? $effective;

        if ($violated === null) {
            return null;
        }

        return new CspViolation(
            violatedDirective: $violated,
            effectiveDirective: $effective,
            blockedUri: self::uri($body, ['blocked-uri', 'blockedURL', 'blockedUri']) ?? 'unbekannt',
            documentUri: self::uri($body, ['document-uri', 'documentURL', 'documentUri']) ?? 'unbekannt',
            disposition: self::disposition($body),
            sourceFile: self::uri($body, ['source-file', 'sourceFile']),
            lineNumber: self::positiveInt($body, ['line-number', 'lineNumber']),
            columnNumber: self::positiveInt($body, ['column-number', 'columnNumber']),
        );
    }

    /**
     * Eine Direktive ist ein Name wie `script-src-elem`, notfalls mit der
     * Quellenliste dahinter (Firefox schickt "script-src 'self'"). Nur das
     * erste Wort wird behalten, sonst sind Zeilen desselben Verstosses
     * verschieden, weil die Policy dazwischen gewachsen ist.
     *
     * @param  array<string, mixed>  $body
     * @param  list<string>  $keys
     */
    private static function directive(array $body, array $keys): ?string
    {
        $wert = self::string($body, $keys);

        if ($wert === null) {
            return null;
        }

        $wert = strtok($wert, ' ');

        if (! is_string($wert) || ! preg_match('/^[a-z0-9\-]+$/i', $wert)) {
            return null;
        }

        return mb_substr(mb_strtolower($wert), 0, self::DIRECTIVE_MAX);
    }

    /**
     * URL ohne Query und Fragment, Schluesselwoerter (`inline`, `eval`,
     * `data`, `blob`, `self`) unveraendert.
     *
     * @param  array<string, mixed>  $body
     * @param  list<string>  $keys
     */
    private static function uri(array $body, array $keys): ?string
    {
        $wert = self::string($body, $keys);

        if ($wert === null) {
            return null;
        }

        $teile = parse_url($wert);

        if (is_array($teile) && isset($teile['scheme'], $teile['host'])) {
            $port = isset($teile['port']) ? ':'.$teile['port'] : '';
            $wert = $teile['scheme'].'://'.$teile['host'].$port.($teile['path'] ?? '');
        }

        return mb_substr($wert, 0, self::URI_MAX);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private static function disposition(array $body): ?string
    {
        $wert = self::string($body, ['disposition']);

        return in_array($wert, ['report', 'enforce'], true) ? $wert : null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<string>  $keys
     */
    private static function positiveInt(array $body, array $keys): ?int
    {
        $wert = $body[self::key($body, $keys)] ?? null;

        if (! is_numeric($wert)) {
            return null;
        }

        $zahl = (int) $wert;

        return $zahl > 0 ? min($zahl, 4_294_967_295) : null;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  list<string>  $keys
     */
    private static function string(array $body, array $keys): ?string
    {
        $wert = $body[self::key($body, $keys)] ?? null;

        if (! is_string($wert) || trim($wert) === '') {
            return null;
        }

        return trim($wert);
    }

    /**
     * Erster Schluessel, den der Rumpf wirklich traegt.
     *
     * @param  array<string, mixed>  $body
     * @param  list<string>  $keys
     */
    private static function key(array $body, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $body)) {
                return $key;
            }
        }

        return $keys[0];
    }
}
