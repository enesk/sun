<?php

declare(strict_types=1);

namespace App\Csp\Support;

use App\Csp\Dto\CspViolation;
use App\Csp\Models\CspReport;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Schreibt CSP-Verstoesse in die zentrale Sammelstelle (#51) — die einzige
 * Stelle, die `csp_reports` befuellt.
 *
 * Entdoppelt wird beim Schreiben, nicht beim Auswerten: ein kaputtes
 * Drittanbieter-Schnipsel im Layout meldet von jedem Seitenaufruf, und das
 * waeren bei 23 Portalen Tausende gleicher Zeilen je Stunde. Schluessel ist
 * {@see CspReport::fingerprint()} (Portal + Direktive + blockierte Quelle),
 * auf der Spalte liegt ein UNIQUE-Index; ein weiterer Treffer erhoeht `hits`
 * und `last_seen_at`.
 *
 * Reihenfolge ist bewusst UPDATE-zuerst: der Normalfall ist der
 * Wiederholungstreffer, und ein UPDATE auf den Unique-Index ist eine Zeile
 * ohne vorheriges SELECT. Greift es nicht, wird eingefuegt; laeuft dabei ein
 * paralleler Request in denselben Schluessel, faengt der Catch die
 * Index-Verletzung und erhoeht statt dessen.
 *
 * Obergrenze: je Portal und Stunde werden hoechstens
 * `csp.report.max_new_rows_per_hour` NEUE Zeilen angelegt. Ohne sie koennte
 * ein Schnipsel mit Cache-Buster in der URL die Tabelle beliebig fuellen,
 * obwohl der Befund immer derselbe ist. Erhoehungen bestehender Zeilen sind
 * nie betroffen — der Zaehler bleibt richtig.
 */
class CspReportCollector
{
    /**
     * @param  Tenant|null  $tenant  Portal laut `tenants.domain`; null bei
     *                               einer zentralen Domain (Admin, Checkout).
     */
    public function __construct(private readonly ?Tenant $tenant = null) {}

    /**
     * @param  list<CspViolation>  $violations
     * @return int Zahl der verarbeiteten Meldungen (neu oder erhoeht)
     */
    public function collect(string $portal, array $violations, ?string $userAgent = null): int
    {
        $verarbeitet = 0;

        foreach ($violations as $violation) {
            if ($this->store($portal, $violation, $userAgent)) {
                $verarbeitet++;
            }
        }

        return $verarbeitet;
    }

    private function store(string $portal, CspViolation $violation, ?string $userAgent): bool
    {
        $fingerprint = CspReport::fingerprint($portal, $violation->violatedDirective, $violation->blockedUri);

        if ($this->bump($fingerprint)) {
            return true;
        }

        if ($this->atCap($portal)) {
            return false;
        }

        try {
            CspReport::query()->create([
                'portal' => mb_substr($portal, 0, 190),
                'tenant_id' => $this->tenant?->getKey(),
                'fingerprint' => $fingerprint,
                'violated_directive' => $violation->violatedDirective,
                'effective_directive' => $violation->effectiveDirective,
                'blocked_uri' => $violation->blockedUri,
                'document_uri' => $violation->documentUri,
                'disposition' => $violation->disposition,
                'source_file' => $violation->sourceFile,
                'line_number' => $violation->lineNumber,
                'column_number' => $violation->columnNumber,
                'user_agent' => $this->trimUserAgent($userAgent),
                'hits' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);

            return true;
        } catch (QueryException) {
            // Paralleler Request war schneller: derselbe Fingerprint, also
            // erhoehen statt melden. Jede andere Ursache bleibt eine Ausnahme.
            return $this->bump($fingerprint);
        }
    }

    /** Erhoeht eine vorhandene Zeile; false, wenn es sie noch nicht gibt. */
    private function bump(string $fingerprint): bool
    {
        return CspReport::query()
            ->where('fingerprint', $fingerprint)
            ->update([
                'hits' => DB::raw('hits + 1'),
                'last_seen_at' => now(),
            ]) > 0;
    }

    private function atCap(string $portal): bool
    {
        $grenze = (int) config('csp.report.max_new_rows_per_hour', 200);

        if ($grenze < 1) {
            return false;
        }

        return CspReport::query()
            ->where('portal', $portal)
            ->where('first_seen_at', '>=', now()->subHour())
            ->count() >= $grenze;
    }

    private function trimUserAgent(?string $userAgent): ?string
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent === '' ? null : mb_substr($userAgent, 0, 255);
    }
}
