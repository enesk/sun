<?php

declare(strict_types=1);

namespace App\Turnstile\Rules;

use App\Models\Tenant;
use App\Turnstile\Config\ResolvedTurnstileConfig;
use App\Turnstile\Config\TurnstileConfigResolver;
use App\Turnstile\Dto\VerificationResult;
use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Enums\VerificationOutcome;
use App\Turnstile\Events\SiteverifyUnreachable;
use App\Turnstile\Exceptions\TurnstileNotConfiguredException;
use App\Turnstile\Services\TurnstileVerifier;
use App\Turnstile\Support\HostnameMatcher;
use App\Turnstile\Support\TurnstileLogger;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;

/**
 * Die EINZIGE Stelle, an der Turnstile durchgesetzt wird (#4,
 * docs/turnstile.md §2/§3).
 *
 * Ein Formular schuetzen heisst: die Rule an die Validierung haengen.
 *
 *     'cf-turnstile-response' => ['required', new TurnstileRule(TurnstileAction::Registration)]
 *
 * Das gilt genauso in Livewire ($this->validate([...]), siehe #6/#7) — der
 * Feldname ist der Hidden-Input, den Turnstile selbst setzt.
 *
 * Ablauf, in dieser Reihenfolge:
 *
 *  1. Konfiguration aufloesen. Modul, Portal oder Aktion aus -> Skipped,
 *     die Rule besteht.
 *  2. Token fehlt oder ist leer -> Failed. Das ist KEIN Ausfall und faellt
 *     deshalb auch bei fail_mode=open durch (docs §5).
 *  3. Siteverify ueber TurnstileVerifier.
 *  4. success, dann Hostname, dann Action — alle drei Pflicht.
 *  5. Error (Siteverify nicht erreichbar) -> Fail-Mode: open laesst durch,
 *     closed weist mit eigener Meldung ab. In beiden Faellen Alarm SUN-TS-011.
 *
 * Jeder Durchlauf schreibt genau eine Zeile in turnstile_verifications, auch
 * der uebersprungene. Das Token landet dort nie.
 */
class TurnstileRule implements DataAwareRule, ValidationRule
{
    /**
     * Macht die Rule implizit: Laravel ruft eine Objekt-Rule bei einem leeren
     * Wert normalerweise gar nicht auf. Ein fehlendes oder leeres Token muss
     * aber abgewiesen werden (docs/turnstile.md §5, "nicht verhandelbar") —
     * sonst schuetzt die Rule nur, wenn der Angreifer irgendetwas mitsendet.
     */
    public bool $implicit = true;

    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * Das Ergebnis des letzten Durchlaufs. Nur fuer den Pruef-Command (#4):
     * so bekommt er Siteverify-Antwort und Rule-Urteil aus einem einzigen
     * Aufruf — ein Token ist bei Cloudflare nur einmal einloesbar.
     */
    private ?VerificationResult $lastResult = null;

    public function __construct(
        private readonly TurnstileAction $action,
        private readonly ?string $emailField = 'email',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function lastResult(): ?VerificationResult
    {
        return $this->lastResult;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $config = $this->resolveConfig();

        if ($config === null) {
            // Produktion ohne echten Secret (TurnstileNotConfiguredException):
            // wie ein Ausfall behandeln — loggen, alarmieren, Fail-Mode.
            $this->handleError(
                $this->placeholderConfig(),
                VerificationResult::unreachable(VerificationResult::CODE_NOT_CONFIGURED),
                $fail,
            );

            return;
        }

        if (! $config->enabled) {
            $this->log($config, VerificationResult::skipped());

            return;
        }

        $token = is_scalar($value) ? trim((string) $value) : '';

        if ($token === '') {
            $this->log($config, VerificationResult::missingToken());
            $fail($this->failureMessage());

            return;
        }

        $result = app(TurnstileVerifier::class)->verify($config, $token, request()?->ip());

        if ($result->outcome === VerificationOutcome::Error) {
            $this->handleError($config, $result, $fail);

            return;
        }

        if (! $result->isPassed()) {
            $this->log($config, $result);
            $fail($this->failureMessage());

            return;
        }

        $result = $this->checkHostname($result, $config);

        if ($result->isPassed()) {
            $result = $this->checkAction($result, $config);
        }

        $this->log($config, $result);

        if (! $result->isPassed()) {
            $fail($this->failureMessage());
        }
    }

    /**
     * Hostname der Antwort gegen die Domains des Portals. Abschaltbar nur, um
     * einen Zwischenfall zu entschaerfen (turnstile.verify_hostname).
     */
    private function checkHostname(VerificationResult $result, ResolvedTurnstileConfig $config): VerificationResult
    {
        if (! config('turnstile.verify_hostname', true) || $this->skipsOriginChecks($config)) {
            return $result;
        }

        if (HostnameMatcher::matches($result->hostname, HostnameMatcher::allowedFor($this->tenant(), $this->requestHost()))) {
            return $result;
        }

        return $result->rejected(VerificationResult::CODE_HOSTNAME_MISMATCH);
    }

    /** Verhindert, dass ein Token aus einem anderen Formular eingereicht wird. */
    private function checkAction(VerificationResult $result, ResolvedTurnstileConfig $config): VerificationResult
    {
        if (! config('turnstile.verify_action', true) || $this->skipsOriginChecks($config)) {
            return $result;
        }

        return $result->action === $this->action->value
            ? $result
            : $result->rejected(VerificationResult::CODE_ACTION_MISMATCH);
    }

    /**
     * Mit den Cloudflare-Testschluesseln sind Hostname und Action nicht
     * pruefbar: Siteverify antwortet dort immer mit `hostname: example.com`
     * und ohne `action`, egal von welcher Domain der Aufruf kommt. Ein
     * Vergleich wuerde auf local und staging jedes Formular blockieren und
     * nichts belegen. In Produktion kann der Fall nicht eintreten — dort wirft
     * der Resolver bei einem Testschluessel (docs/turnstile.md §4).
     */
    private function skipsOriginChecks(ResolvedTurnstileConfig $config): bool
    {
        return $config->usesTestKeys;
    }

    /**
     * Siteverify nicht erreichbar: loggen, alarmieren, Fail-Mode anwenden.
     * Ein fehlender Token kommt hier nie an (docs §5, "nicht verhandelbar").
     */
    private function handleError(ResolvedTurnstileConfig $config, VerificationResult $result, Closure $fail): void
    {
        $failOpen = $config->failsOpen();

        $this->log($config, $result);

        $event = new SiteverifyUnreachable($config, $result, $failOpen);
        Log::warning('Turnstile: Siteverify nicht erreichbar ('.SiteverifyUnreachable::ALERT_CODE.')', $event->toLogContext());
        SiteverifyUnreachable::dispatch($config, $result, $failOpen);

        if (! $failOpen) {
            $fail($this->unavailableMessage());
        }
    }

    private function resolveConfig(): ?ResolvedTurnstileConfig
    {
        try {
            return TurnstileConfigResolver::for($this->action);
        } catch (TurnstileNotConfiguredException $exception) {
            Log::error('Turnstile: '.$exception->getMessage());

            return null;
        }
    }

    /**
     * Ersatz-Konfiguration fuer den Fall, dass der Resolver geworfen hat: der
     * Fail-Mode muss trotzdem bekannt sein, und das Log braucht die Aktion.
     */
    private function placeholderConfig(): ResolvedTurnstileConfig
    {
        $failMode = mb_strtolower(trim((string) config('turnstile.fail_mode'))) === ResolvedTurnstileConfig::FAIL_MODE_CLOSED
            ? ResolvedTurnstileConfig::FAIL_MODE_CLOSED
            : ResolvedTurnstileConfig::FAIL_MODE_OPEN;

        $tenant = $this->tenant();

        return new ResolvedTurnstileConfig(
            action: $this->action,
            tenantId: $tenant !== null ? (int) $tenant->getKey() : null,
            enabled: true,
            mode: $this->action->defaultMode(),
            siteKey: '',
            secretKey: '',
            failMode: $failMode,
            usesTestKeys: false,
            widgetGroup: TurnstileConfigResolver::groupForHost($this->requestHost()),
        );
    }

    private function log(ResolvedTurnstileConfig $config, VerificationResult $result): void
    {
        $this->lastResult = $result;

        $request = request();

        app(TurnstileLogger::class)->record(
            config: $config,
            result: $result,
            expectedHostname: HostnameMatcher::expectedFor($this->tenant(), $this->requestHost()),
            ip: $request?->ip(),
            userAgent: $request?->userAgent(),
            email: $this->email(),
        );
    }

    /**
     * Nur fuer die Korrelation in #8 — als HMAC, nie im Klartext. Kommt aus den
     * mitvalidierten Feldern, deshalb DataAwareRule.
     */
    private function email(): ?string
    {
        if ($this->emailField === null) {
            return null;
        }

        $email = data_get($this->data, $this->emailField);

        return is_string($email) && $email !== '' ? $email : null;
    }

    private function tenant(): ?Tenant
    {
        if (! function_exists('tenancy') || ! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }

    private function requestHost(): ?string
    {
        $request = request();

        if ($request === null) {
            return null;
        }

        $host = $request->getHost();

        return $host !== '' ? $host : null;
    }

    private function failureMessage(): string
    {
        return __('turnstile.failed');
    }

    private function unavailableMessage(): string
    {
        return __('turnstile.unavailable');
    }
}
