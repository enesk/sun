<?php

declare(strict_types=1);

namespace App\Turnstile\Dto;

use App\Turnstile\Enums\VerificationOutcome;

/**
 * Ergebnis genau eines Pruefversuchs (#4, docs/turnstile.md §3).
 *
 * Unveraenderlich. Die Rohfelder (success, hostname, action, challenge_ts,
 * error_codes) kommen eins zu eins aus der Siteverify-Antwort; `outcome` ist
 * die Bewertung. Der TurnstileVerifier setzt nur Passed (success), Failed
 * (not success) und Error (Endpunkt nicht erreichbar) — die Pruefung von
 * Hostname und Action macht TurnstileRule und schiebt das Ergebnis mit
 * {@see self::rejected()} auf Failed.
 *
 * Das Token selbst steht hier nie drin und wird nie geloggt.
 */
final class VerificationResult
{
    /** Kein oder leeres Token eingereicht — nie ein Ausfall, immer Failed. */
    public const CODE_MISSING_TOKEN = 'missing-input-response';

    /** Siteverify nicht erreichbar: Zeitueberschreitung, 5xx, unlesbare Antwort. */
    public const CODE_UNREACHABLE = 'siteverify_unreachable';

    /** Der Circuit Breaker hat den Aufruf gar nicht erst versucht. */
    public const CODE_BREAKER_OPEN = 'siteverify_breaker_open';

    /** In Produktion fehlt ein echter Secret (TurnstileNotConfiguredException). */
    public const CODE_NOT_CONFIGURED = 'turnstile_not_configured';

    /** Hostname der Antwort gehoert nicht zum Portal der Anfrage. */
    public const CODE_HOSTNAME_MISMATCH = 'hostname-mismatch';

    /** Action der Antwort gehoert nicht zum erwarteten Formular. */
    public const CODE_ACTION_MISMATCH = 'action-mismatch';

    /** Token war schon verbraucht. Zaehlt als Failed, nicht als Error. */
    public const CODE_DUPLICATE = 'timeout-or-duplicate';

    /**
     * @param  list<string>  $errorCodes
     */
    public function __construct(
        public readonly VerificationOutcome $outcome,
        public readonly bool $success,
        public readonly ?string $hostname = null,
        public readonly ?string $action = null,
        public readonly ?string $challengeTs = null,
        public readonly array $errorCodes = [],
        public readonly ?int $durationMs = null,
        public readonly ?string $cdata = null,
        public readonly ?int $httpStatus = null,
    ) {}

    /**
     * Antwort von Cloudflare, bereits als Array dekodiert.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromSiteverify(array $payload, int $durationMs, ?int $httpStatus = null): self
    {
        $success = (bool) ($payload['success'] ?? false);

        return new self(
            outcome: $success ? VerificationOutcome::Passed : VerificationOutcome::Failed,
            success: $success,
            hostname: self::stringOrNull($payload['hostname'] ?? null),
            action: self::stringOrNull($payload['action'] ?? null),
            challengeTs: self::stringOrNull($payload['challenge_ts'] ?? null),
            errorCodes: self::codes($payload['error-codes'] ?? $payload['error_codes'] ?? []),
            durationMs: $durationMs,
            cdata: self::stringOrNull($payload['cdata'] ?? null),
            httpStatus: $httpStatus,
        );
    }

    /** Siteverify hat nicht geantwortet — hier entscheidet der Fail-Mode. */
    public static function unreachable(string $code = self::CODE_UNREACHABLE, ?int $durationMs = null, ?int $httpStatus = null): self
    {
        return new self(
            outcome: VerificationOutcome::Error,
            success: false,
            errorCodes: [$code],
            durationMs: $durationMs,
            httpStatus: $httpStatus,
        );
    }

    /** Nicht geprueft: Modul aus, Portal aus oder Aktion aus. */
    public static function skipped(): self
    {
        return new self(outcome: VerificationOutcome::Skipped, success: true);
    }

    /** Kein Token im Request. Auch bei fail_mode=open eine Abweisung. */
    public static function missingToken(): self
    {
        return new self(
            outcome: VerificationOutcome::Failed,
            success: false,
            errorCodes: [self::CODE_MISSING_TOKEN],
        );
    }

    /**
     * Nachtraegliche Abweisung durch die Rule (Hostname, Action). Behaelt alle
     * Rohfelder der Antwort — auch `success`, denn Cloudflare hat die Aufgabe
     * ja bestanden; abgewiesen wird sie hier. Das Urteil steht in `outcome`.
     */
    public function rejected(string $code): self
    {
        return new self(
            outcome: VerificationOutcome::Failed,
            success: $this->success,
            hostname: $this->hostname,
            action: $this->action,
            challengeTs: $this->challengeTs,
            errorCodes: array_values(array_unique([...$this->errorCodes, $code])),
            durationMs: $this->durationMs,
            cdata: $this->cdata,
            httpStatus: $this->httpStatus,
        );
    }

    /** Hat die Pruefung bestanden? Entscheidend ist das Urteil, nicht `success`. */
    public function isPassed(): bool
    {
        return $this->outcome === VerificationOutcome::Passed;
    }

    public function hasErrorCode(string $code): bool
    {
        return in_array($code, $this->errorCodes, true);
    }

    /** Darf die Anfrage weiterlaufen? Error haengt am Fail-Mode. */
    public function allowsRequest(bool $failOpen): bool
    {
        return $this->outcome->allowsRequest($failOpen);
    }

    /**
     * Fuer Log und Command — ohne Token, ohne Secret.
     *
     * @return array<string, mixed>
     */
    public function toLogContext(): array
    {
        return [
            'outcome' => $this->outcome->value,
            'success' => $this->success,
            'hostname' => $this->hostname,
            'action' => $this->action,
            'challenge_ts' => $this->challengeTs,
            'error_codes' => $this->errorCodes,
            'duration_ms' => $this->durationMs,
            'http_status' => $this->httpStatus,
        ];
    }

    /**
     * @return list<string>
     */
    private static function codes(mixed $codes): array
    {
        if (is_string($codes)) {
            $codes = [$codes];
        }

        if (! is_array($codes)) {
            return [];
        }

        $clean = [];

        foreach ($codes as $code) {
            if (is_scalar($code) && trim((string) $code) !== '') {
                $clean[] = mb_substr(trim((string) $code), 0, 64);
            }
        }

        return array_values(array_unique($clean));
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 255);
    }
}
