<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\VerificationOutcome;
use App\Turnstile\Models\TurnstileVerification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Schreibt einen Treffer der Tiefenverteidigung in turnstile_verifications
 * (#8) — dieselbe Tabelle wie die Turnstile-Pruefungen, damit #9 und #12 nur
 * eine Quelle auswerten.
 *
 * Immer `outcome = failed`, der Grund steht als Code in error_codes_json
 * ({@see SpamCode}). `duration_ms` bleibt leer: es gab keinen Siteverify-Aufruf.
 *
 * Absichtlich NICHT ueber App\Turnstile\Support\TurnstileLogger: der braucht
 * eine aufgeloeste Turnstile-Konfiguration, und die kann in Produktion werfen
 * (TurnstileNotConfiguredException). Genau dann — Turnstile kaputt — muss
 * diese Schicht aber noch schreiben koennen.
 *
 * Datenschutz wie dort: kein Klartext. IP und E-Mail nur als HMAC-SHA256
 * (TurnstileVerification::hashIp()/hashEmail()).
 *
 * Die Tabelle liegt in der Tenant-DB. Ohne Portalkontext (zentrale Domain,
 * Artisan) gibt es sie nicht; dann wandert der Vorgang nur ins Laravel-Log.
 * Jeder Schreibfehler wird geschluckt — ein Log-Problem darf kein Formular
 * kippen.
 */
class AntiSpamLogger
{
    /**
     * @param  list<string>  $codes
     */
    public function record(
        TurnstileAction $action,
        array $codes,
        ?string $email = null,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        $request = request();
        $ip ??= $request?->ip();
        $userAgent ??= $request?->userAgent();

        $context = [
            'action' => $action->value,
            'outcome' => VerificationOutcome::Failed->value,
            'error_codes' => $codes,
            'quelle' => 'antispam',
        ];

        if (! config('turnstile.log.enabled', true)) {
            return;
        }

        if (! $this->inTenantContext()) {
            Log::info('Antispam-Treffer ohne Portalkontext', $context);

            return;
        }

        try {
            TurnstileVerification::create([
                'action' => $action->value,
                'outcome' => VerificationOutcome::Failed->value,
                'error_codes_json' => $codes === [] ? null : array_values($codes),
                'hostname_reported' => null,
                'hostname_expected' => $request?->getHost(),
                'ip_hash' => TurnstileVerification::hashIp($ip),
                'user_agent' => TurnstileVerification::trimUserAgent($userAgent),
                'email_hash' => TurnstileVerification::hashEmail($email),
                'duration_ms' => null,
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Antispam-Log konnte nicht geschrieben werden: '.$exception->getMessage(), $context);
        }
    }

    private function inTenantContext(): bool
    {
        return function_exists('tenancy') && tenancy()->initialized;
    }
}
