<?php

declare(strict_types=1);

namespace App\Turnstile\Services;

use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Dto\VerificationResult;
use App\Turnstile\Support\CircuitBreaker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Der einzige Ort im Projekt, der Cloudflare Siteverify aufruft (#4,
 * docs/turnstile.md §2/§3).
 *
 * Aufrufen darf das ausschliesslich App\Turnstile\Rules\TurnstileRule oder der
 * Pruef-Command App\Console\Commands\TurnstileVerify. Wer ein Formular
 * schuetzen will, haengt die Rule an die Validierung — damit bleibt die
 * Durchsetzung an einer Stelle. {@see self::guardCaller()} setzt das zur
 * Laufzeit durch, damit die Regel nicht nur in der Doku steht.
 *
 * Verhalten:
 *
 * * POST application/x-www-form-urlencoded mit secret, response und (falls
 *   bekannt) remoteip; Timeout aus `turnstile.timeout_seconds`.
 * * Ein Wiederholungsversuch, und zwar nur bei einem Netzwerkfehler
 *   (ConnectionException). Ein 4xx/5xx wird NICHT wiederholt: Cloudflare hat
 *   geantwortet, und ein verbrauchtes Token wuerde beim zweiten Mal
 *   `timeout-or-duplicate` liefern.
 * * Vor dem Aufruf fragt er den Circuit Breaker. Ist der offen, gibt es sofort
 *   Error statt `timeout_seconds` Wartezeit.
 * * Der Secret steht nie in einer Log-Zeile und nie in einer Ausnahme.
 */
class TurnstileVerifier
{
    /**
     * @var list<class-string>
     */
    private const ALLOWED_CALLERS = [
        \App\Turnstile\Rules\TurnstileRule::class,
        \App\Console\Commands\TurnstileVerify::class,
    ];

    public function __construct(private readonly CircuitBreaker $breaker) {}

    public function verify(ResolvedTurnstileConfig $config, string $token, ?string $remoteIp = null): VerificationResult
    {
        $this->guardCaller();

        if ($token === '') {
            return VerificationResult::missingToken();
        }

        if ($this->breaker->isOpen()) {
            return VerificationResult::unreachable(VerificationResult::CODE_BREAKER_OPEN);
        }

        $payload = array_filter([
            'secret' => $config->secretKey,
            'response' => $token,
            'remoteip' => $remoteIp,
        ], fn (?string $value): bool => $value !== null && $value !== '');

        $startedAt = hrtime(true);
        $result = $this->post($payload, $config);
        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($result === null) {
            $this->breaker->recordFailure();

            return VerificationResult::unreachable(durationMs: $durationMs);
        }

        $this->breaker->recordSuccess();

        [$body, $status] = $result;

        return VerificationResult::fromSiteverify($body, $durationMs, $status);
    }

    /** Zustand des Breakers fuer den Pruef-Command. */
    public function breakerState(): string
    {
        return $this->breaker->state();
    }

    /**
     * @param  array<string, string>  $payload
     * @return array{0: array<string, mixed>, 1: int}|null null = nicht erreichbar
     */
    private function post(array $payload, ResolvedTurnstileConfig $config): ?array
    {
        $url = (string) config('turnstile.siteverify_url');
        $timeout = max(1, (int) config('turnstile.timeout_seconds', 5));
        $attempts = 2;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = Http::asForm()
                    ->timeout($timeout)
                    ->connectTimeout(min($timeout, 3))
                    ->withHeaders(['Accept' => 'application/json'])
                    ->post($url, $payload);
            } catch (ConnectionException $exception) {
                // Zeitueberschreitung oder DNS/TCP-Problem: einmal nachfassen,
                // dann aufgeben. Der Token ist dabei nicht verbraucht.
                if ($attempt < $attempts) {
                    continue;
                }

                $this->report($config, 'Siteverify nicht erreichbar', $exception);

                return null;
            } catch (Throwable $exception) {
                $this->report($config, 'Siteverify-Aufruf fehlgeschlagen', $exception);

                return null;
            }

            if ($response->failed()) {
                $this->report($config, "Siteverify antwortet mit HTTP {$response->status()}");

                return null;
            }

            $body = $response->json();

            if (! is_array($body) || ! array_key_exists('success', $body)) {
                $this->report($config, 'Siteverify-Antwort ist unlesbar');

                return null;
            }

            /** @var array<string, mixed> $body */
            return [$body, $response->status()];
        }

        return null;
    }

    /** Nie den Secret, nie das Token — nur Zustand und Grund. */
    private function report(ResolvedTurnstileConfig $config, string $message, ?Throwable $exception = null): void
    {
        Log::warning("Turnstile: {$message}", $config->toLogContext() + [
            'exception' => $exception !== null ? $exception::class : null,
        ]);
    }

    /**
     * Haelt die Architekturregel aus docs/turnstile.md §2 zur Laufzeit: ausser
     * der Rule und dem Command ruft niemand Siteverify auf.
     */
    private function guardCaller(): void
    {
        // Erste fremde Klasse im Aufrufpfad: die muss erlaubt sein. Rahmen
        // ohne Klasse (freie Funktionen, Closures im globalen Raum) sagen
        // nichts aus und werden uebersprungen.
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) as $frame) {
            $class = $frame['class'] ?? null;

            if ($class === null || $class === self::class) {
                continue;
            }

            foreach (self::ALLOWED_CALLERS as $allowed) {
                if ($class === $allowed || is_subclass_of($class, $allowed)) {
                    return;
                }
            }

            break;
        }

        throw new LogicException(
            'Turnstile: Siteverify wird ausschliesslich ueber App\\Turnstile\\Rules\\TurnstileRule '
            .'aufgerufen. Formulare werden mit der Rule geschuetzt, nicht mit dem Verifier '
            .'(docs/turnstile.md, Abschnitt 2).'
        );
    }
}
