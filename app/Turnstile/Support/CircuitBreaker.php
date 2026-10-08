<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Circuit Breaker light fuer den Siteverify-Aufruf (#4).
 *
 * Faellt Cloudflare aus, soll nicht jede Anfrage erst `timeout_seconds` warten,
 * bevor der Fail-Mode greift. Nach `threshold` Fehlern innerhalb von
 * `window_seconds` ist der Schalter fuer `cooldown_seconds` offen: der Verifier
 * ruft gar nicht mehr an und liefert sofort Error.
 *
 * Zwei Schluessel, kein Lua und keine Transaktion: ein Zaehler mit TTL fuer das
 * Fenster und ein Merker fuer die Sperre. Ein verlorener Increment unter Last
 * kostet nur einen weiteren Versuch — das ist der Preis dafuer, dass der
 * Breaker selbst nie eine Anfrage kippen kann.
 *
 * Store: `config('turnstile.circuit_breaker.store')`, Vorgabe `file`. Bewusst
 * NICHT der Standard-Store: in Produktion ist das `database`, und im
 * Tenant-Kontext zeigt die Standardverbindung auf die Tenant-DB, die keine
 * cache-Tabelle hat. Der file-Store wechselt je Portal (SetTenantStorageUrl),
 * der Breaker zaehlt also je Portal — fuer den Zweck genau richtig, weil der
 * Fail-Mode ohnehin je Portal gilt.
 *
 * Jeder Cache-Zugriff ist gekapselt: ein kaputter Cache darf kein Formular
 * blockieren.
 */
class CircuitBreaker
{
    private const PREFIX = 'turnstile:breaker:';

    public function __construct(private readonly string $name = 'siteverify') {}

    /** Ist der Schalter offen, also der Aufruf gerade zu ueberspringen? */
    public function isOpen(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        return (bool) $this->safely(fn (Repository $cache): bool => (bool) $cache->get($this->openKey(), false), false);
    }

    /** Siteverify hat nicht geantwortet. Ab `threshold` im Fenster wird gesperrt. */
    public function recordFailure(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->safely(function (Repository $cache): null {
            $window = $this->windowSeconds();
            $key = $this->failureKey();

            // add() setzt den Startwert nur, wenn der Schluessel fehlt — damit
            // bekommt das Fenster seine TTL und wandert nicht mit jedem Fehler.
            if ($cache->add($key, 1, $window)) {
                $count = 1;
            } else {
                $count = (int) $cache->increment($key);
            }

            if ($count >= $this->threshold()) {
                $cache->put($this->openKey(), true, $this->cooldownSeconds());
                $cache->forget($key);
            }

            return null;
        }, null);
    }

    /** Siteverify hat geantwortet. Fenster und Sperre fallen sofort. */
    public function recordSuccess(): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->safely(function (Repository $cache): null {
            $cache->forget($this->failureKey());
            $cache->forget($this->openKey());

            return null;
        }, null);
    }

    /** Fuer den Command und #12: Zustand ohne Nebenwirkung. */
    public function state(): string
    {
        if (! $this->enabled()) {
            return 'aus';
        }

        return $this->isOpen() ? 'offen (Siteverify wird uebersprungen)' : 'geschlossen';
    }

    private function enabled(): bool
    {
        return (bool) config('turnstile.circuit_breaker.enabled', true);
    }

    private function threshold(): int
    {
        return max(1, (int) config('turnstile.circuit_breaker.threshold', 5));
    }

    private function windowSeconds(): int
    {
        return max(1, (int) config('turnstile.circuit_breaker.window_seconds', 60));
    }

    private function cooldownSeconds(): int
    {
        return max(1, (int) config('turnstile.circuit_breaker.cooldown_seconds', 60));
    }

    private function failureKey(): string
    {
        return self::PREFIX.$this->name.':fehler';
    }

    private function openKey(): string
    {
        return self::PREFIX.$this->name.':offen';
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
            $store = config('turnstile.circuit_breaker.store', 'file');

            return $callback(Cache::store(is_string($store) && $store !== '' ? $store : null));
        } catch (Throwable) {
            return $fallback;
        }
    }
}
