<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use App\Models\Tenant;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\VerificationOutcome;
use App\Turnstile\Models\TurnstileVerification;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Kennzahlen des Verifikations-Logs ueber alle Portale (#9, #12).
 *
 * Das Log liegt je Portal in der Tenant-DB, eine netzweite Zahl gibt es also
 * nur als Summe. Je Portal laeuft genau eine gruppierte Abfrage ({@see
 * self::portal()}): alle angefragten Zeitfenster fallen als bedingte Summen in
 * derselben Abfrage an, damit nicht mehrere Durchlaeufe ueber alle Portale
 * nötig sind. Index: (action, outcome, created_at).
 *
 * Diese eine Methode ist die gemeinsame Quelle fuer alles, was das Log
 * auswertet: Widget (netzweit, 1 h/24 h/7 Tage), Monitor (#12, letzte Stunde je
 * Portal) und Tagesbericht (#12, ein Kalendertag je Portal). Keine zweite
 * Query-Logik.
 *
 * Das netzweite Ergebnis ist 60 Sekunden gecacht — das Widget soll die
 * Übersicht nicht ausbremsen und der Betreiber sieht die Lage trotzdem nahezu
 * in Echtzeit. Monitor und Bericht rechnen ungecacht, sie laufen je Lauf
 * einmal.
 *
 * Portale, deren Datenbank oder Tabelle nicht lesbar ist (noch nicht migriert,
 * Import laeuft), zaehlen nicht mit und werden namentlich gemeldet, statt die
 * ganze Auswertung scheitern zu lassen.
 */
final class TurnstileStats
{
    public const CACHE_KEY = 'turnstile.stats.network';

    public const CACHE_SECONDS = 60;

    /** Ab diesem Anteil Fehler-Outcomes in einer Stunde wird rot gewarnt. */
    public const ERROR_ALERT_SHARE = 0.3;

    /**
     * @return array{
     *     generated_at: string,
     *     portals: int,
     *     unreadable: list<string>,
     *     windows: array<string, array<string, int>>,
     *     actions: array<string, array<string, array<string, int>>>,
     *     per_portal: list<array{tenant_id: int, tenant: string, windows: array<string, array<string, int>>}>
     * }
     */
    public static function network(): array
    {
        $stats = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => self::compute());

        return is_array($stats) ? $stats : self::compute();
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Eine gruppierte Abfrage je Portal, beliebig viele Zeitfenster.
     *
     * Die Verbindung `tenant` wird auf das Portal gerichtet und bleibt stehen —
     * der Aufrufer raeumt mit {@see TurnstileLogConnection::forget()} ab, wenn
     * er alle Portale durch hat.
     *
     * @param  array<string, array{0: DateTimeInterface, 1: DateTimeInterface}>  $fenster  Name => [von (inklusive), bis (exklusive)]
     * @return array{windows: array<string, array<string, int>>, actions: array<string, array<string, array<string, int>>>}
     *
     * @throws Throwable wenn das Log des Portals nicht lesbar ist
     */
    public static function portal(Tenant $tenant, array $fenster): array
    {
        $windows = [];
        $actions = [];

        foreach (array_keys($fenster) as $name) {
            $windows[$name] = self::emptyBucket();
        }

        foreach (TurnstileAction::cases() as $action) {
            $actions[$action->value] = $windows;
        }

        if ($fenster === []) {
            return ['windows' => $windows, 'actions' => $actions];
        }

        $spalten = [];
        $bindings = [];
        $namen = [];
        $von = null;
        $bis = null;

        foreach (array_values($fenster) as $index => [$start, $ende]) {
            $spalten[] = "sum(case when created_at >= ? and created_at < ? then 1 else 0 end) as fenster_{$index}";
            $bindings[] = self::datum($start);
            $bindings[] = self::datum($ende);
            $namen[$index] = array_keys($fenster)[$index];

            $von = $von === null || self::datum($start) < $von ? self::datum($start) : $von;
            $bis = $bis === null || self::datum($ende) > $bis ? self::datum($ende) : $bis;
        }

        TurnstileLogConnection::point($tenant);

        $rows = TurnstileVerification::query()
            ->toBase()
            ->selectRaw('action, outcome, '.implode(', ', $spalten), $bindings)
            ->where('created_at', '>=', $von)
            ->where('created_at', '<', $bis)
            ->groupBy('action', 'outcome')
            ->get();

        foreach ($rows as $row) {
            $outcome = VerificationOutcome::tryFrom((string) $row->outcome);

            if ($outcome === null) {
                continue;
            }

            $action = TurnstileAction::tryFromValue((string) $row->action);

            foreach ($namen as $index => $name) {
                $anzahl = (int) ($row->{"fenster_{$index}"} ?? 0);

                if ($anzahl === 0) {
                    continue;
                }

                $windows[$name]['total'] += $anzahl;
                $windows[$name][$outcome->value] += $anzahl;

                if ($action !== null) {
                    $actions[$action->value][$name]['total'] += $anzahl;
                    $actions[$action->value][$name][$outcome->value] += $anzahl;
                }
            }
        }

        return ['windows' => $windows, 'actions' => $actions];
    }

    /**
     * Anteil eines Ergebnisses am Fenster, 0.0 bis 1.0.
     *
     * @param  array<string, mixed>  $bucket
     */
    public static function share(array $bucket, string $key): float
    {
        $total = (int) ($bucket['total'] ?? 0);

        return $total > 0 ? ((int) ($bucket[$key] ?? 0)) / $total : 0.0;
    }

    /**
     * @param  array<string, mixed>  $bucket
     */
    public static function percent(array $bucket, string $key): string
    {
        return self::formatShare(self::share($bucket, $key));
    }

    /** Anteil als Prozenttext, fuer Werte die schon als Anteil vorliegen. */
    public static function formatShare(float $anteil): string
    {
        return number_format($anteil * 100, 1, ',', '.').' %';
    }

    /**
     * Fehlerquote der letzten Stunde ueber der Schwelle? Bewusst ohne
     * Mindestmenge: ein Ausfall von Siteverify faellt auch bei wenigen
     * Versuchen auf, und genau darauf soll der Hinweis aufmerksam machen.
     * Die Alarm-Mail (#12) hat dagegen eine Mindestmenge, damit nicht ein
     * einzelner Fehler in einer stillen Stunde eine Mail ausloest.
     *
     * @param  array<string, mixed>  $stats
     */
    public static function hasErrorAlert(array $stats): bool
    {
        $bucket = $stats['windows']['1h'] ?? [];

        return (int) ($bucket['total'] ?? 0) > 0
            && self::share($bucket, VerificationOutcome::Error->value) > self::ERROR_ALERT_SHARE;
    }

    /**
     * Portale mit Fehlern im Fenster, die meisten Fehler zuerst (#19).
     *
     * Sortiert nach der absoluten Zahl, nicht nach der Quote: bei
     * fail_mode=open ist das die Zahl der Anfragen, die ungeprueft
     * durchgelaufen sind — 9 Fehler von 100 wiegen schwerer als 1 von 1.
     *
     * Ohne Mindestmenge und ohne Schwelle: die Kacheln erscheinen nur, wenn
     * ueberhaupt ein Fehler da ist, und genau dann will der Betrieb wissen,
     * welches Portal es trifft — die netzweite Quote verwaescht einen Ausfall,
     * der nur eine Widget-Gruppe betrifft.
     *
     * @param  array<string, mixed>  $stats  Ergebnis von {@see self::network()}
     * @return list<array{tenant_id: int, tenant: string, total: int, errors: int, share: float}>
     */
    public static function errorPortals(array $stats, string $fenster = '1h'): array
    {
        $portale = [];

        foreach ((array) ($stats['per_portal'] ?? []) as $portal) {
            $bucket = (array) ($portal['windows'][$fenster] ?? []);
            $fehler = (int) ($bucket[VerificationOutcome::Error->value] ?? 0);

            if ($fehler === 0) {
                continue;
            }

            $portale[] = [
                'tenant_id' => (int) ($portal['tenant_id'] ?? 0),
                'tenant' => (string) ($portal['tenant'] ?? ''),
                'total' => (int) ($bucket['total'] ?? 0),
                'errors' => $fehler,
                'share' => self::share($bucket, VerificationOutcome::Error->value),
            ];
        }

        usort($portale, static fn (array $a, array $b): int => $b['errors'] <=> $a['errors']);

        return $portale;
    }

    /**
     * @return array<string, int>
     */
    public static function emptyBucket(): array
    {
        $bucket = ['total' => 0];

        foreach (VerificationOutcome::cases() as $outcome) {
            $bucket[$outcome->value] = 0;
        }

        return $bucket;
    }

    /**
     * Zwei Eimer aufsummieren (netzweite Summe, Portalsummen im Bericht).
     *
     * @param  array<string, int>  $ziel
     * @param  array<string, int>  $quelle
     * @return array<string, int>
     */
    public static function addBuckets(array $ziel, array $quelle): array
    {
        foreach ($quelle as $key => $wert) {
            $ziel[$key] = (int) ($ziel[$key] ?? 0) + (int) $wert;
        }

        return $ziel;
    }

    /**
     * @return array<string, mixed>
     */
    private static function compute(): array
    {
        $jetzt = now();
        $fenster = [
            '1h' => [$jetzt->copy()->subHour(), $jetzt],
            '24h' => [$jetzt->copy()->subDay(), $jetzt],
            '7d' => [$jetzt->copy()->subDays(7), $jetzt],
        ];

        $windows = [];
        $actions = [];

        foreach (array_keys($fenster) as $name) {
            $windows[$name] = self::emptyBucket();
        }

        foreach (TurnstileAction::cases() as $action) {
            $actions[$action->value] = $windows;
        }

        $portale = 0;
        $unreadable = [];
        $jePortal = [];

        foreach (Tenant::query()->orderBy('name')->get() as $tenant) {
            /** @var Tenant $tenant */
            try {
                $portal = self::portal($tenant, $fenster);
            } catch (Throwable $exception) {
                $unreadable[] = (string) $tenant->name;
                Log::info('Turnstile-Kennzahlen: Portal nicht lesbar', [
                    'tenant_id' => (int) $tenant->getKey(),
                    'exception' => $exception::class,
                ]);

                continue;
            }

            $portale++;

            // Dieselbe Abfrage traegt die Portalwerte schon — sie hier
            // mitzunehmen kostet keine weitere Runde ueber alle Portale (#19,
            // Fehlerquote je Portal im Widget).
            $jePortal[] = [
                'tenant_id' => (int) $tenant->getKey(),
                'tenant' => (string) $tenant->name,
                'windows' => $portal['windows'],
            ];

            foreach ($portal['windows'] as $name => $bucket) {
                $windows[$name] = self::addBuckets($windows[$name], $bucket);
            }

            foreach ($portal['actions'] as $action => $buckets) {
                foreach ($buckets as $name => $bucket) {
                    $actions[$action][$name] = self::addBuckets($actions[$action][$name], $bucket);
                }
            }
        }

        TurnstileLogConnection::forget();

        return [
            'generated_at' => $jetzt->toIso8601String(),
            'portals' => $portale,
            'unreadable' => $unreadable,
            'windows' => $windows,
            'actions' => $actions,
            'per_portal' => $jePortal,
        ];
    }

    private static function datum(DateTimeInterface $zeit): string
    {
        return $zeit->format('Y-m-d H:i:s');
    }
}
