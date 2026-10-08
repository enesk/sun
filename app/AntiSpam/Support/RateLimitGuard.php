<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use App\Turnstile\Enums\TurnstileAction;
use App\Turnstile\Models\TurnstileVerification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-Limits der Tiefenverteidigung (#8).
 *
 * Zwei Benutzungsarten, beide auf denselben Zaehlern und derselben
 * Konfiguration (config/antispam.php, je Portal ueberschreibbar):
 *
 *  1. {@see self::enforce()} in der Controller-Aktion: prueft, zaehlt, loggt
 *     und wirft bei einem Treffer die 429-Antwort aus
 *     {@see self::throttleResponse()} (deutsche Meldung, Retry-After).
 *     So benutzen es RegisterController und LoginController.
 *
 *  2. {@see self::exceeded()} / {@see self::hit()} fuer Livewire, wo es keine
 *     Route gibt, an die ein Middleware passt. Dort ist ein 429 die
 *     schlechtere Antwort: der Besucher bliebe vor einem toten Formular
 *     stehen. Die Komponente bekommt stattdessen die Restzeit und zeigt
 *     dieselbe Meldung am Feld.
 *
 * WARUM NICHT `throttle:antispam-registration` als Middleware, obwohl die
 * benannten Limiter in {@see self::register()} dafuer bereitstehen?
 * ThrottleRequests steht in Laravels $middlewarePriority weit vorn und laeuft
 * damit VOR der Tenancy-Middleware. Im Limiter waere das Portal dann noch
 * nicht initialisiert: der Schluessel bekaeme fuer jedes Portal denselben
 * Praefix, und die Portal-Grenzen aus tenant_turnstile_settings wuerden gar
 * nicht gelesen. In der Aktion steht das Portal. Die benannten Limiter bleiben
 * registriert — fuer Routen ohne Portalbezug ist die Middleware der kuerzere
 * Weg, und sie sind die dokumentierte Form der Grenzen.
 *
 * Der Schluessel traegt IMMER den Portal-Praefix, damit die Limits je Portal
 * zaehlen und ein Portal die anderen nicht mitsperrt. Die IP steht nie im
 * Klartext im Schluessel: gezaehlt wird auf dem HMAC-Hash aus
 * TurnstileVerification::hashIp() — derselbe Hash wie im Log, also auch in
 * #9 wiederzufinden.
 */
class RateLimitGuard
{
    public const REGISTRATION = 'antispam-registration';

    public const LOGIN = 'antispam-login';

    public const COMPANY_LISTING = 'antispam-company-listing';

    /** Anfrage-Dialog, exklusiver Weg (#22). */
    public const LEAD_REQUEST = 'antispam-lead-request';

    /** Bewertung abgeben (#22). */
    public const REVIEW = 'antispam-review';

    /** Stellenbewerbung (#22). */
    public const JOB_APPLICATION = 'antispam-job-application';

    /** Newsletter-Anmeldung (#22). */
    public const NEWSLETTER = 'antispam-newsletter';

    /** Korrekturvorschlag am Firmenprofil (#22). */
    public const SUGGEST_EDIT = 'antispam-suggest-edit';

    /*
    | Profiluebernahme, App\Livewire\Portal\ClaimModal und die eingebettete
    | Fassung ClaimForm (#26). Drei Eintrittspunkte, drei Limiter: der
    | Registrierungsreiter legt ein Konto an, der Anmeldereiter probiert ein
    | Passwort, der Bestaetigungsweg erzeugt einen Uebernahme-Antrag. Ein
    | gemeinsamer Zaehler wuerde den harmlosesten Weg mit der Grenze des
    | gefaehrlichsten sperren — und umgekehrt.
    */

    /** Registrierungsreiter der Uebernahme (#26). */
    public const CLAIM_REGISTRATION = 'antispam-claim-registration';

    /** Anmeldereiter der Uebernahme (#26). */
    public const CLAIM_LOGIN = 'antispam-claim-login';

    /** Uebernahme-Antrag selbst, angemeldeter Weg (#26). */
    public const CLAIM = 'antispam-claim';

    /** Einspruch gegen eine fremde Uebernahme (#26). */
    public const CLAIM_DISPUTE = 'antispam-claim-dispute';

    /**
     * Nachweis-Upload nach der Uebernahme (#27). Kein Captcha: der Weg setzt
     * einen angemeldeten Nutzer mit offenem Antrag voraus, ein Limit genuegt.
     */
    public const CLAIM_UPLOAD = 'antispam-claim-upload';

    /** Benannter Limiter -> Abschnitt in config('antispam.limits'). */
    private const SECTIONS = [
        self::REGISTRATION => 'registration',
        self::LOGIN => 'login',
        self::COMPANY_LISTING => 'company_listing',
        self::LEAD_REQUEST => 'lead_request',
        self::REVIEW => 'review',
        self::JOB_APPLICATION => 'job_application',
        self::NEWSLETTER => 'newsletter',
        self::SUGGEST_EDIT => 'suggest_edit',
        self::CLAIM_REGISTRATION => 'claim_registration',
        self::CLAIM_LOGIN => 'claim_login',
        self::CLAIM => 'claim',
        self::CLAIM_DISPUTE => 'claim_dispute',
        self::CLAIM_UPLOAD => 'claim_upload',
    ];

    /**
     * Registriert die benannten Limiter. Aufruf in
     * App\Providers\AppServiceProvider::boot().
     */
    public static function register(): void
    {
        RateLimiter::for(self::REGISTRATION, static fn (Request $request): array => [
            self::limit('registration.ip', self::REGISTRATION, 'ip', self::ipHash($request)),
            self::limit('registration.email', self::REGISTRATION, 'email', self::emailHash($request->input('email'))),
        ]);

        RateLimiter::for(self::LOGIN, static fn (Request $request): array => [
            self::limit(
                'login.ip_email',
                self::LOGIN,
                'ip_email',
                self::ipHash($request).'|'.self::emailHash($request->input('email')),
            ),
        ]);

        RateLimiter::for(self::COMPANY_LISTING, static fn (Request $request): array => [
            self::limit('company_listing.ip', self::COMPANY_LISTING, 'ip', self::ipHash($request)),
            self::limit('company_listing.user', self::COMPANY_LISTING, 'user', self::userSignal()),
        ]);

        RateLimiter::for(self::LEAD_REQUEST, static fn (Request $request): array => [
            self::limit('lead_request.ip', self::LEAD_REQUEST, 'ip', self::ipHash($request)),
        ]);

        RateLimiter::for(self::REVIEW, static fn (Request $request): array => [
            self::limit('review.ip', self::REVIEW, 'ip', self::ipHash($request)),
        ]);

        RateLimiter::for(self::JOB_APPLICATION, static fn (Request $request): array => [
            self::limit('job_application.ip', self::JOB_APPLICATION, 'ip', self::ipHash($request)),
            self::limit('job_application.email', self::JOB_APPLICATION, 'email', self::emailHash($request->input('email'))),
        ]);

        RateLimiter::for(self::NEWSLETTER, static fn (Request $request): array => [
            self::limit('newsletter.ip', self::NEWSLETTER, 'ip', self::ipHash($request)),
            self::limit('newsletter.email', self::NEWSLETTER, 'email', self::emailHash($request->input('email'))),
        ]);

        RateLimiter::for(self::SUGGEST_EDIT, static fn (Request $request): array => [
            self::limit('suggest_edit.ip', self::SUGGEST_EDIT, 'ip', self::ipHash($request)),
        ]);

        RateLimiter::for(self::CLAIM_REGISTRATION, static fn (Request $request): array => [
            self::limit('claim_registration.ip', self::CLAIM_REGISTRATION, 'ip', self::ipHash($request)),
            self::limit('claim_registration.email', self::CLAIM_REGISTRATION, 'email', self::emailHash($request->input('email'))),
        ]);

        RateLimiter::for(self::CLAIM_LOGIN, static fn (Request $request): array => [
            self::limit(
                'claim_login.ip_email',
                self::CLAIM_LOGIN,
                'ip_email',
                self::ipHash($request).'|'.self::emailHash($request->input('email')),
            ),
        ]);

        RateLimiter::for(self::CLAIM, static fn (Request $request): array => [
            self::limit('claim.ip', self::CLAIM, 'ip', self::ipHash($request)),
            self::limit('claim.user', self::CLAIM, 'user', self::userSignal()),
        ]);

        RateLimiter::for(self::CLAIM_DISPUTE, static fn (Request $request): array => [
            self::limit('claim_dispute.ip', self::CLAIM_DISPUTE, 'ip', self::ipHash($request)),
        ]);

        RateLimiter::for(self::CLAIM_UPLOAD, static fn (Request $request): array => [
            self::limit('claim_upload.ip', self::CLAIM_UPLOAD, 'ip', self::ipHash($request)),
            self::limit('claim_upload.user', self::CLAIM_UPLOAD, 'user', self::userSignal()),
        ]);
    }

    /**
     * Pruefen, zaehlen, loggen — und bei einem Treffer die 429-Antwort werfen.
     * Der Weg fuer klassische Controller-Aktionen.
     *
     * `$action` null heisst: nur ins Laravel-Log, nicht nach
     * turnstile_verifications. Das ist der Fall beim Login — zu
     * TurnstileAction gibt es keine Aktion `login`, und die Spalte ist auf die
     * vier bestehenden Werte festgelegt (sie werden als Enum gelesen).
     *
     * @param  array<string, string|null>  $signals
     *
     * @throws HttpResponseException 429 mit deutscher Meldung und Retry-After
     */
    public static function enforce(string $limiter, ?TurnstileAction $action, array $signals = []): void
    {
        $sekunden = self::exceeded($limiter, $signals);

        if ($sekunden === null) {
            self::hit($limiter, $signals);

            return;
        }

        if ($action !== null) {
            self::log($limiter, $action, $signals['email'] ?? null);
        } else {
            Log::info('Antispam: Rate-Limit erreicht', ['limiter' => $limiter, 'sekunden' => $sekunden]);
        }

        throw new HttpResponseException(self::throttleResponse(
            request(),
            ['Retry-After' => $sekunden, 'X-RateLimit-Limit' => 0, 'X-RateLimit-Remaining' => 0],
        ));
    }

    /**
     * Pruefen und zaehlen in einem Schritt. Null = erlaubt, sonst die
     * Sekunden bis zum naechsten Versuch.
     *
     * Fuer Formulare, bei denen jeder Absendeversuch zaehlen soll. Wer erst
     * nach dem erfolgreichen Anlegen zaehlen will (damit ein Tippfehler in
     * der Validierung nicht aufs Limit geht), nimmt {@see self::exceeded()}
     * und danach {@see self::hit()}.
     *
     * @param  array<string, string|null>  $signals  z.B. ['email' => 'a@b.de']
     */
    public static function check(string $limiter, array $signals = []): ?int
    {
        $sekunden = self::exceeded($limiter, $signals);

        if ($sekunden !== null) {
            return $sekunden;
        }

        self::hit($limiter, $signals);

        return null;
    }

    /**
     * Ist ein Limit schon ausgereizt? Zaehlt NICHT mit — sonst verlaengert
     * jeder Fehlversuch die Sperre endlos.
     *
     * @param  array<string, string|null>  $signals
     */
    public static function exceeded(string $limiter, array $signals = []): ?int
    {
        foreach (self::activeLimits($limiter, $signals) as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key, $limit['max'])) {
                return max(1, RateLimiter::availableIn($key));
            }
        }

        return null;
    }

    /**
     * Einen Versuch zaehlen, ohne zu pruefen.
     *
     * @param  array<string, string|null>  $signals
     */
    public static function hit(string $limiter, array $signals = []): void
    {
        foreach (self::activeLimits($limiter, $signals) as $key => $limit) {
            RateLimiter::hit($key, $limit['decay']);
        }
    }

    /**
     * Alle aktiven Teil-Limits eines Limiters: Schluessel -> Grenze und
     * Verfallszeit in Sekunden. `max` 0 heisst abgeschaltet.
     *
     * @param  array<string, string|null>  $signals
     * @return array<string, array{max: int, decay: int}>
     */
    private static function activeLimits(string $limiter, array $signals): array
    {
        if (! AntiSpamConfig::enabled()) {
            return [];
        }

        $section = self::SECTIONS[$limiter] ?? null;

        if ($section === null) {
            return [];
        }

        $limits = AntiSpamConfig::get("limits.{$section}", []);
        $limits = is_array($limits) ? $limits : [];
        $aktiv = [];

        foreach (array_keys($limits) as $name) {
            $name = (string) $name;
            $max = AntiSpamConfig::int("limits.{$section}.{$name}.max", 0);

            if ($max <= 0) {
                continue;
            }

            $signal = self::signalFor($name, $signals);

            // Ohne Signal kein Limit: ein leerer Schluesselteil wuerde alle
            // Besucher auf denselben Zaehler legen (so waere `user` fuer
            // Gaeste ein netzweiter Sperrknopf).
            if ($signal === null || $signal === '') {
                continue;
            }

            $minutes = max(1, AntiSpamConfig::int("limits.{$section}.{$name}.minutes", 60));

            $aktiv[self::key($limiter, $name, $signal)] = [
                'max' => $max,
                'decay' => $minutes * 60,
            ];
        }

        return $aktiv;
    }

    /**
     * Treffer eines Limits ins Log (#9/#12). Ruft der Aufrufer selbst — der
     * benannte Limiter sieht den Portalkontext, die Zeile soll aber nur
     * einmal entstehen, nicht je Teil-Limit.
     */
    public static function log(string $limiter, TurnstileAction $action, ?string $email = null): void
    {
        app(AntiSpamLogger::class)->record(
            action: $action,
            codes: [SpamCode::RATE_LIMIT.':'.$limiter],
            email: $email,
        );
    }

    /**
     * Schluessel: Limiter, Teil-Limit, Portal, Signal. Der Portalteil macht
     * die Limits portaleigen.
     */
    public static function key(string $limiter, string $name, ?string $signal): string
    {
        return implode('|', [$limiter, $name, self::tenantPrefix(), (string) $signal]);
    }

    private static function limit(string $path, string $limiter, string $name, ?string $signal): Limit
    {
        $max = AntiSpamConfig::int("limits.{$path}.max", 0);
        $minutes = max(1, AntiSpamConfig::int("limits.{$path}.minutes", 60));

        if (! AntiSpamConfig::enabled() || $max <= 0 || $signal === null || $signal === '') {
            return Limit::none();
        }

        return Limit::perMinutes($minutes, $max)
            ->by(self::key($limiter, $name, $signal))
            ->response(static fn (Request $request, array $headers) => self::throttleResponse($request, $headers));
    }

    /**
     * Antwort bei einem ueberschrittenen Limit: 429, deutsche Meldung,
     * Retry-After. `$headers` kommt von Illuminate\Routing\Middleware\
     * ThrottleRequests und enthaelt Retry-After und X-RateLimit-*; sie werden
     * unveraendert durchgegeben.
     *
     * @param  array<string, mixed>  $headers
     */
    public static function throttleResponse(Request $request, array $headers): Response
    {
        $sekunden = max(1, (int) ($headers['Retry-After'] ?? 60));
        $minuten = max(1, (int) ceil($sekunden / 60));
        $meldung = trans_choice('antispam.throttled', $minuten, ['minuten' => $minuten]);

        if ($request->expectsJson()) {
            return response()->json(['message' => $meldung], 429, $headers);
        }

        return response()->view('errors.antispam-throttled', [
            'meldung' => $meldung,
            'sekunden' => $sekunden,
        ], 429, $headers);
    }

    /**
     * @param  array<string, string|null>  $signals
     */
    private static function signalFor(string $name, array $signals): ?string
    {
        return match ($name) {
            'ip' => $signals['ip'] ?? self::ipHash(request()),
            // Angemeldete teilen sich oft eine IP (Firmennetz) und wechseln
            // sie auch (Mobilfunk). Fuer sie zaehlt die Nutzerkennung, nicht
            // die Herkunft — ohne dieses Signal blieb der Doppel- und
            // Mehrfacheintrag eines Kontos ungebremst (#16).
            'user' => $signals['user'] ?? self::userSignal(),
            'email' => self::emailHash($signals['email'] ?? null),
            'ip_email' => ($signals['ip'] ?? self::ipHash(request())).'|'.self::emailHash($signals['email'] ?? null),
            default => $signals[$name] ?? null,
        };
    }

    /**
     * Signal des angemeldeten Nutzers; null fuer Gaeste — dann greift das
     * Limit nicht (siehe activeLimits()).
     */
    private static function userSignal(): ?string
    {
        $id = Auth::id();

        return $id === null ? null : 'user:'.$id;
    }

    private static function ipHash(?Request $request): string
    {
        return TurnstileVerification::hashIp($request?->ip()) ?? 'ohne-ip';
    }

    private static function emailHash(mixed $email): string
    {
        if (! is_scalar($email)) {
            return 'ohne-mail';
        }

        return TurnstileVerification::hashEmail(trim((string) $email)) ?? 'ohne-mail';
    }

    /**
     * Portalteil des Schluessels. Erst der Tenant-Key, dann — falls die
     * Tenancy noch nicht initialisiert ist (benannter Limiter in der
     * Middleware) — der Hostname der Anfrage. Erst zuletzt "central": ein
     * gemeinsamer Praefix fuer alle Portale wuerde die Limits
     * portaluebergreifend zaehlen.
     */
    private static function tenantPrefix(): string
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return (string) (tenant()?->getTenantKey() ?? 'central');
        }

        $host = mb_strtolower(trim((string) request()?->getHost()), 'UTF-8');

        return $host !== '' ? $host : 'central';
    }
}
