<?php

declare(strict_types=1);

namespace App\Turnstile\Support;

use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Dto\VerificationResult;
use App\Turnstile\Models\TurnstileVerification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Schreibt genau eine Zeile je Pruefversuch in turnstile_verifications (#4,
 * ausgewertet in #9/#12).
 *
 * Datenschutz (docs/turnstile.md §4): kein Token, keine Klartext-IP, keine
 * Klartext-E-Mail — nur HMAC-SHA256 ueber TurnstileVerification::hashIp() und
 * hashEmail().
 *
 * Die Tabelle liegt in der Tenant-DB. Ohne Tenant-Kontext (zentrale Domain,
 * Artisan ohne --tenant) gibt es keine Verbindung dorthin; dann wandert der
 * Vorgang nur in das Laravel-Log. Jeder Schreibfehler wird geschluckt: ein
 * Log-Problem darf keine Registrierung kippen.
 */
class TurnstileLogger
{
    public function record(
        ResolvedTurnstileConfig $config,
        VerificationResult $result,
        ?string $expectedHostname = null,
        ?string $ip = null,
        ?string $userAgent = null,
        ?string $email = null,
    ): void {
        if (! config('turnstile.log.enabled', true)) {
            return;
        }

        if (! $this->inTenantContext()) {
            Log::info('Turnstile-Pruefung ohne Portalkontext', $config->toLogContext() + $result->toLogContext());

            return;
        }

        try {
            TurnstileVerification::create([
                'action' => $config->action->value,
                'outcome' => $result->outcome->value,
                'error_codes_json' => $result->errorCodes === [] ? null : $result->errorCodes,
                'hostname_reported' => $result->hostname,
                'hostname_expected' => $expectedHostname,
                'ip_hash' => TurnstileVerification::hashIp($ip),
                'user_agent' => TurnstileVerification::trimUserAgent($userAgent),
                'email_hash' => TurnstileVerification::hashEmail($email),
                'duration_ms' => $result->durationMs,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Turnstile-Log konnte nicht geschrieben werden: '.$exception->getMessage(), $config->toLogContext() + $result->toLogContext());
        }
    }

    private function inTenantContext(): bool
    {
        return function_exists('tenancy') && tenancy()->initialized;
    }
}
