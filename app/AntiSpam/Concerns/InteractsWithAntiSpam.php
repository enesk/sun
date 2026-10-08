<?php

declare(strict_types=1);

namespace App\AntiSpam\Concerns;

use App\AntiSpam\SpamGuard;
use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\RateLimitGuard;
use App\AntiSpam\Support\TimingToken;
use App\Turnstile\Enums\TurnstileAction;

/**
 * Tiefenverteidigung in einer Livewire-Komponente (#8).
 *
 * In Livewire gibt es keinen Request, aus dem sich Honeypot und Zeitstempel
 * lesen liessen — die Werte muessen in Properties stehen. Der Trait legt sie
 * an, der Blade-Teil bindet den Honeypot:
 *
 *     use InteractsWithAntiSpam;
 *     ...
 *     <x-antispam-fields wire />
 *
 * Beim Absenden:
 *
 *     if ($this->antiSpamRejects(TurnstileAction::CompanyListing, $this->email)) {
 *         $this->done = true;   // Erfolg vorspielen, nichts anlegen
 *         return;
 *     }
 *
 * Der Zeitstempel wird in mount() gesetzt und wandert NICHT durch das DOM: in
 * Livewire ueberlebt die Property den Request, und Livewire schuetzt sie mit
 * seiner Pruefsumme. Verschluesselt ist sie trotzdem — derselbe Wert wie im
 * klassischen Formular, damit nur eine Leseart existiert.
 */
trait InteractsWithAntiSpam
{
    /** Honeypot; muss leer bleiben. Gebunden von <x-antispam-fields wire />. */
    public string $antispamHoneypot = '';

    /** Verschluesselter Zeitstempel des ersten Renderns. */
    public string $antispamTime = '';

    /** Livewire ruft das beim Mounten der Komponente automatisch mit auf. */
    public function mountInteractsWithAntiSpam(): void
    {
        $this->antispamTime = TimingToken::issue();
    }

    /**
     * Honeypot und Ausfuellzeit pruefen und bei einem Treffer loggen. True
     * heisst: Erfolg vorspielen, nichts anlegen (so verlangt es #8).
     */
    protected function antiSpamRejects(TurnstileAction $action, ?string $email = null): bool
    {
        return app(SpamGuard::class)->rejects(
            action: $action,
            honeypot: $this->antispamHoneypot,
            timingToken: $this->antispamTime,
            email: $email,
        );
    }

    /**
     * Ist ein Rate-Limit ausgereizt? Null heisst erlaubt, sonst die Sekunden
     * bis zum naechsten Versuch — in Livewire wird daraus eine Meldung am
     * Feld und kein 429, sonst stuende der Besucher vor einem toten Formular.
     *
     * Gezaehlt wird NICHT; dafuer gibt es {@see self::antiSpamCount()}, das
     * erst nach dem erfolgreichen Anlegen laeuft. So kostet ein Tippfehler in
     * der Validierung keinen Versuch.
     *
     * @param  array<string, string|null>  $signals
     */
    protected function antiSpamLimit(string $limiter, array $signals = []): ?int
    {
        return RateLimitGuard::exceeded($limiter, $signals);
    }

    /**
     * Einen Versuch auf das Limit zaehlen.
     *
     * @param  array<string, string|null>  $signals
     */
    protected function antiSpamCount(string $limiter, array $signals = []): void
    {
        RateLimitGuard::hit($limiter, $signals);
    }

    /**
     * Meldung zu einer Restzeit in Sekunden — dieselbe wie im 429.
     */
    protected function antiSpamThrottleMessage(int $sekunden): string
    {
        $minuten = max(1, (int) ceil($sekunden / 60));

        return trans_choice('antispam.throttled', $minuten, ['minuten' => $minuten]);
    }

    /** Ist die Schicht ueberhaupt an? Nur fuer Sonderfaelle in der Ansicht. */
    protected function antiSpamEnabled(): bool
    {
        return AntiSpamConfig::enabled();
    }
}
