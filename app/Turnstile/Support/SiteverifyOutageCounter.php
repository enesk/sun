<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Zaehler fuer Siteverify-Ausfaelle in einem kurzen Fenster (#19).
 *
 * Ein einzelner Ausfall ist Rauschen, zwanzig in fuenf Minuten sind ein
 * Vorfall. Gezaehlt wird im Cache, nicht im Verifikations-Log: der Listener
 * laeuft mitten in einer Anfrage, die gerade schon in eine Zeitueberschreitung
 * gelaufen ist — eine Aggregation ueber `turnstile_verifications` auf den
 * rotierenden Platten der Produktion wuerde dieselbe Anfrage ein zweites Mal
 * ausbremsen. Die genaue Fehlerquote liefert ohnehin das Log
 * ({@see TurnstileStats::portal()}); hier geht es nur um "jetzt reicht es".
 *
 * Zwei Zaehler je Portal mit gemeinsamer Fensterdauer (`fehler` und davon
 * `durchgelassen` bei fail_mode=open) und ein Merker fuer die Ruhezeit nach
 * einer Meldung. Dieselbe Bauart wie {@see CircuitBreaker}: add() setzt die
 * TTL, increment() zaehlt, das Fenster wandert nicht mit.
 *
 * Store: `config('turnstile.alerts.siteverify_unreachable.store')`, Vorgabe
 * `file`. Bewusst nicht der Standard-Store (in Produktion `database`, dessen
 * Verbindung im Tenant-Kontext auf die Tenant-DB ohne cache-Tabelle zeigt).
 * Der file-Store wechselt je Portal (App\Listeners\Tenant\SetTenantStorageUrl),
 * der Zaehler laeuft also je Portal — passend dazu, dass auch Schluessel und
 * Fail-Mode je Portal gelten.
 *
 * Jeder Cache-Zugriff ist gekapselt: ein kaputter Cache darf keine
 * Registrierung und keine Firmeneintragung kippen.
 */
final class SiteverifyOutageCounter
{
    private const PREFIX = 'turnstile:unreachable:';

    public function __construct(private readonly ?int $tenantId) {}

    /**
     * Einen Ausfall zaehlen und den Stand des laufenden Fensters liefern.
     *
     * @param  bool  $requestWasAllowed  fail_mode=open: die Anfrage lief ungeprueft durch
     * @return array{errors: int, allowed: int}
     */
    public function record(bool $requestWasAllowed): array
    {
        return $this->safely(function (Repository $cache) use ($requestWasAllowed): array {
            $fenster = $this->windowSeconds();

            $fehler = $this->bump($cache, $this->key('fehler'), $fenster);
            $durchgelassen = $requestWasAllowed
                ? $this->bump($cache, $this->key('durchgelassen'), $fenster)
                : (int) $cache->get($this->key('durchgelassen'), 0);

            return ['errors' => $fehler, 'allowed' => $durchgelassen];
        }, ['errors' => 0, 'allowed' => 0]);
    }

    /**
     * Darf jetzt gemeldet werden? true nur beim ersten Mal in der Ruhezeit.
     * Cache::add ist atomar — zwei gleichzeitige Anfragen melden nicht beide.
     */
    public function claim(): bool
    {
        return (bool) $this->safely(
            fn (Repository $cache): bool => $cache->add($this->key('gemeldet'), true, $this->cooldownSeconds()),
            false,
        );
    }

    /** Nach einer Meldung beginnt das Fenster neu, sonst meldet es sofort wieder. */
    public function reset(): void
    {
        $this->safely(function (Repository $cache): null {
            $cache->forget($this->key('fehler'));
            $cache->forget($this->key('durchgelassen'));

            return null;
        }, null);
    }

    public function threshold(): int
    {
        return max(1, (int) config('turnstile.alerts.siteverify_unreachable.threshold', 20));
    }

    public function windowSeconds(): int
    {
        return max(1, (int) config('turnstile.alerts.siteverify_unreachable.window_seconds', 300));
    }

    private function cooldownSeconds(): int
    {
        return max(1, (int) config('turnstile.alerts.siteverify_unreachable.cooldown_seconds', 1800));
    }

    private function bump(Repository $cache, string $key, int $fenster): int
    {
        // add() setzt den Startwert nur, wenn der Schluessel fehlt — damit
        // bekommt das Fenster seine TTL und wandert nicht mit jedem Fehler.
        if ($cache->add($key, 1, $fenster)) {
            return 1;
        }

        return (int) $cache->increment($key);
    }

    private function key(string $zweck): string
    {
        return self::PREFIX.($this->tenantId ?? 'zentral').':'.$zweck;
    }

    /**
     * @template TReturn
     *
     * @param  callable(Repository): TReturn  $callback
     * @param  TReturn  $fallback
     * @return TReturn
     */
    private function safely(callable $callback, mixed $fallback): mixed
    {
        try {
            $store = config('turnstile.alerts.siteverify_unreachable.store', 'file');

            return $callback(Cache::store(is_string($store) && $store !== '' ? $store : null));
        } catch (Throwable) {
            return $fallback;
        }
    }
}
