<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Config\TurnstileConfigResolver;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\VerificationOutcome;
use App\Turnstile\Events\SiteverifyUnreachable;
use App\Turnstile\Exceptions\TurnstileNotConfiguredException;
use App\Turnstile\Rules\TurnstileRule;
use App\Turnstile\Services\TurnstileVerifier;
use App\Turnstile\Support\HostnameMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Prueft ein Turnstile-Token von Hand (#4, Abnahme auf Staging).
 *
 * Zeigt drei Dinge getrennt: die aufgeloeste Konfiguration, die rohe
 * Siteverify-Antwort und das Ergebnis der Rule — damit laesst sich eine
 * Abweisung zuordnen, ohne ein Formular abzuschicken.
 *
 *   php artisan turnstile:verify XXXX.DUMMY.TOKEN.XXXX --action=registration
 *   php artisan turnstile:verify <token> --action=company_listing --tenant=fahrschulefinder.de
 *
 * Ohne --tenant gilt nur config/turnstile.php (keine Tenant-Einstellungen, kein
 * Log in turnstile_verifications, Hostname-Pruefung gegen den CLI-Host).
 *
 * Das Token wird nie geloggt und nie gespeichert; der Secret erscheint in der
 * Ausgabe nur als "gesetzt / nicht gesetzt".
 */
class TurnstileVerify extends Command
{
    protected $signature = 'turnstile:verify
        {token : Das cf-turnstile-response-Token}
        {--action=registration : registration|company_listing|lead_request|contact}
        {--tenant= : Tenant-Domain, UUID oder ID; ohne Angabe ohne Portalkontext}';

    protected $description = 'Prueft ein Turnstile-Token gegen Siteverify und zeigt das Ergebnis der TurnstileRule';

    public function handle(): int
    {
        $action = TurnstileAction::tryFromValue((string) $this->option('action'));

        if ($action === null) {
            $this->error('Unbekannte Aktion. Erlaubt: '.implode(', ', TurnstileAction::values()));

            return self::FAILURE;
        }

        $tenantIdentifier = trim((string) $this->option('tenant'));

        if ($tenantIdentifier === '') {
            return $this->check($action, (string) $this->argument('token'));
        }

        $tenant = Tenant::query()
            ->where('domain', $tenantIdentifier)
            ->orWhere('uuid', $tenantIdentifier)
            ->orWhere('id', $tenantIdentifier)
            ->first();

        if ($tenant === null) {
            $this->error("Portal \"{$tenantIdentifier}\" nicht gefunden.");

            return self::FAILURE;
        }

        // Im Portalkontext: Tenant-Einstellungen gelten, und die Pruefung
        // landet in turnstile_verifications dieses Portals.
        return (int) $tenant->run(fn (): int => $this->check($action, (string) $this->argument('token'), $tenant));
    }

    private function check(TurnstileAction $action, string $token, ?Tenant $tenant = null): int
    {
        TurnstileConfigResolver::flush();

        try {
            $config = TurnstileConfigResolver::for($action);
        } catch (TurnstileNotConfiguredException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->showConfig($config, $tenant);

        $this->line('Circuit Breaker: '.app(TurnstileVerifier::class)->breakerState());

        // Nur EIN Durchlauf: ein Token ist bei Cloudflare einmal einloesbar.
        // Ein zweiter Siteverify-Aufruf mit demselben Token liefert
        // timeout-or-duplicate — deshalb holt sich der Command die rohe
        // Antwort aus der Rule statt selbst zu verifizieren.
        $field = 'cf-turnstile-response';
        $rule = new TurnstileRule($action);

        $validator = Validator::make(
            [$field => trim($token)],
            [$field => ['required', $rule]],
        );

        $abgewiesen = $validator->fails();
        $result = $rule->lastResult();

        $this->newLine();
        $this->components->info('Siteverify-Antwort');

        if ($result === null) {
            $this->line('  kein Ergebnis — die Rule kam nicht bis zum Aufruf.');
        } else {
            $this->table(['Feld', 'Wert'], [
                ['success', $result->success ? 'true' : 'false'],
                ['hostname', $result->hostname ?? '—'],
                ['action', $result->action ?? '—'],
                ['challenge_ts', $result->challengeTs ?? '—'],
                ['error-codes', $result->errorCodes === [] ? '—' : implode(', ', $result->errorCodes)],
                ['cdata', $result->cdata ?? '—'],
                ['HTTP-Status', $result->httpStatus !== null ? (string) $result->httpStatus : '—'],
                ['Dauer', $result->durationMs !== null ? "{$result->durationMs} ms" : '—'],
                ['Bewertung', $result->outcome->value],
            ]);
        }

        $this->newLine();
        $this->components->info('Ergebnis der TurnstileRule');

        if ($abgewiesen) {
            foreach ($validator->errors()->get($field) as $message) {
                $this->line("  abgewiesen: {$message}");
            }

            $this->newLine();
            $this->warn('Die Rule weist das Token ab.');

            return self::FAILURE;
        }

        $this->newLine();

        if ($result?->outcome === VerificationOutcome::Skipped) {
            $this->info('Die Rule prueft nicht (skipped) — Modul, Portal oder Aktion ist aus.');

            return self::SUCCESS;
        }

        if ($result?->outcome === VerificationOutcome::Error) {
            $this->warn('Die Rule laesst durch, aber nur wegen fail_mode=open (Alarm '
                .SiteverifyUnreachable::ALERT_CODE.'). Siteverify hat nicht geantwortet.');

            return self::SUCCESS;
        }

        $this->info('Die Rule laesst das Token durch.');

        return self::SUCCESS;
    }

    private function showConfig(ResolvedTurnstileConfig $config, ?Tenant $tenant): void
    {
        $this->components->info('Aufgeloeste Konfiguration');

        $allowed = HostnameMatcher::allowedFor($tenant, request()->getHost());

        $this->table(['Einstellung', 'Wert'], [
            ['Aktion', $config->action->value],
            ['Portal', $tenant !== null ? "{$tenant->name} ({$tenant->domain})" : 'ohne Portalkontext'],
            ['geprueft', $config->enabled ? 'ja' : 'nein — die Rule ueberspringt (skipped)'],
            ['Darstellung', $config->mode->value],
            ['Sitekey', $config->siteKey !== '' ? mb_substr($config->siteKey, 0, 10).'…' : 'nicht gesetzt'],
            ['Secret', $config->secretKey !== '' ? 'gesetzt' : 'nicht gesetzt'],
            ['Testschluessel', $config->usesTestKeys ? 'ja' : 'nein'],
            ['Widget-Gruppe', $config->widgetGroup],
            ['Fail-Mode', $config->failMode],
            ['Hostname-Pruefung', config('turnstile.verify_hostname') ? 'an' : 'AUS'],
            ['Action-Pruefung', config('turnstile.verify_action') ? 'an' : 'AUS'],
            ['erlaubte Hostnames', $allowed === [] ? '—' : implode(', ', $allowed)],
            ['Log', config('turnstile.log.enabled') ? 'turnstile_verifications' : 'aus'],
        ]);
    }
}
