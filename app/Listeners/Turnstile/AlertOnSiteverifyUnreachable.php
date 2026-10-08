<?php

declare(strict_types=1);

namespace App\Listeners\Turnstile;

use App\Models\Tenant;
use App\Turnstile\Events\SiteverifyUnreachable;
use App\Turnstile\Mail\BotProtectionAlert;
use App\Turnstile\Monitoring\VerificationAlert;
use App\Turnstile\Support\BotProtectionRecipients;
use App\Turnstile\Support\SiteverifyOutageCounter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Alarm SUN-TS-011: Siteverify ist nicht erreichbar (#19, fachlich zu #12).
 *
 * Haengt an {@see SiteverifyUnreachable}, das {@see \App\Turnstile\Rules\TurnstileRule}
 * bei jedem `VerificationOutcome::Error` ausloest — in beiden Fail-Modes.
 *
 * Bewusst NICHT eine Mail je Ereignis: ein einzelner Ausfall ist Rauschen
 * (Zeitueberschreitung bei einer Anfrage, 5xx bei Cloudflare). Gemeldet wird
 * erst, wenn `turnstile.alerts.siteverify_unreachable.threshold` Ausfaelle
 * innerhalb von `window_seconds` zusammenkommen (Vorgabe: 20 in 5 Minuten),
 * und danach hoechstens einmal je `cooldown_seconds` (Vorgabe: 30 Minuten) —
 * ein Ausfall dauert in der Regel laenger als eine Meldung.
 *
 * Warum neben dem stuendlichen `turnstile:monitor` (#12): der Monitor sieht
 * denselben Vorfall erst beim naechsten Lauf, also bis zu 60 Minuten spaeter.
 * Bei `fail_mode = open` ist das die Zeit, in der Registrierung und
 * Firmeneintragung ungeprueft durchlaufen — `requestWasAllowed = true` ist die
 * einzige Spur davon. Der Monitor bleibt die Aufsicht ueber die Quote, dieser
 * Listener ist der schnelle Alarm.
 *
 * Laeuft absichtlich synchron und ohne ShouldQueue: er faellt mitten in eine
 * Anfrage, die ohnehin schon auf eine Zeitueberschreitung gewartet hat, und
 * macht nichts Teures — ein Cache-Zaehler, und nur beim Ueberschreiten der
 * Schwelle eine Mail in die Queue. Ueber die Queue zu zaehlen waere zudem
 * zerbrechlich: faellt Siteverify waehrend eines Queue-Staus aus, kaeme der
 * Alarm Stunden spaeter.
 *
 * Jeder Fehler hier wird geschluckt und geloggt. Ein Alarm darf niemals das
 * Formular kippen, das er ueberwacht.
 *
 * Registrierung: Event-Discovery ueber `app/Listeners` (kein Event::listen,
 * das wuerde doppelt registrieren). Pruefen mit
 * `php artisan event:list --event=SiteverifyUnreachable`.
 */
class AlertOnSiteverifyUnreachable
{
    public function handle(SiteverifyUnreachable $event): void
    {
        try {
            $this->bewerten($event);
        } catch (Throwable $exception) {
            Log::warning('Turnstile: Alarm '.SiteverifyUnreachable::ALERT_CODE.' konnte nicht ausgewertet werden', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function bewerten(SiteverifyUnreachable $event): void
    {
        if (! (bool) config('turnstile.alerts.enabled', true)) {
            return;
        }

        $zaehler = new SiteverifyOutageCounter($event->config->tenantId);
        $stand = $zaehler->record($event->requestWasAllowed);

        if ($stand['errors'] < $zaehler->threshold()) {
            return;
        }

        if (! $zaehler->claim()) {
            return;
        }

        // Das Fenster beginnt neu, sonst steht der Zaehler nach der Ruhezeit
        // sofort wieder ueber der Schwelle, ohne dass ein neuer Ausfall dazu
        // gekommen ist.
        $zaehler->reset();

        $alarm = $this->alarm($event, $stand['errors'], $stand['allowed'], $zaehler->windowSeconds());

        Log::warning('Turnstile-Alarm '.SiteverifyUnreachable::ALERT_CODE, $alarm->toArray() + $event->toLogContext());

        $empfaenger = BotProtectionRecipients::emails();

        if ($empfaenger === []) {
            Log::warning('Turnstile-Alarm ohne Empfaenger', $alarm->toArray());

            return;
        }

        // queue() und nicht send(): der Versand haengt nicht an der Anfrage
        // eines Nutzers, der gerade ein Formular abgeschickt hat.
        Mail::to($empfaenger)->queue(new BotProtectionAlert($alarm));
    }

    private function alarm(SiteverifyUnreachable $event, int $fehler, int $durchgelassen, int $fenster): VerificationAlert
    {
        $minuten = max(1, (int) round($fenster / 60));
        $offen = $event->config->failsOpen();

        $folge = $offen
            ? __('Fail-Mode "open": Registrierung und Firmeneintragung laufen so lange ohne Turnstile durch (:allowed Anfragen bisher). Honeypot, Mindest-Ausfüllzeit und Rate-Limits greifen weiter.', [
                'allowed' => $durchgelassen,
            ])
            : __('Fail-Mode "closed": Registrierung und Firmeneintragung sind so lange blockiert — echte Nutzer kommen nicht durch.');

        return new VerificationAlert(
            type: VerificationAlert::TYPE_SITEVERIFY_UNREACHABLE,
            tenantId: (int) ($event->config->tenantId ?? 0),
            tenantName: $this->portal($event->config->tenantId),
            headline: __('Siteverify nicht erreichbar: :errors Ausfälle in :minutes Minuten', [
                'errors' => $fehler,
                'minutes' => $minuten,
            ]),
            message: __('Cloudflare Siteverify hat :errors Prüfungen innerhalb von :minutes Minuten nicht beantwortet (Zeitüberschreitung, 5xx oder unlesbare Antwort). Entweder stört Cloudflare, oder der Server kommt nicht hinaus. Alarmcode :code.', [
                'errors' => $fehler,
                'minutes' => $minuten,
                'code' => SiteverifyUnreachable::ALERT_CODE,
            ]),
            consequence: $folge,
            numbers: [
                __('Ausfälle im Fenster') => $fehler,
                __('Davon durchgelassen') => $durchgelassen,
                __('Fenster') => __(':minutes Minuten', ['minutes' => $minuten]),
                __('Formular') => $event->config->action->label(),
                __('Fail-Mode') => $event->config->failMode,
                __('Widget-Gruppe') => $event->config->widgetGroup,
                __('Fehlercodes') => implode(', ', $event->result->errorCodes) ?: '—',
            ],
        );
    }

    /**
     * Portalname fuer Betreff und Mail. Im Portalkontext steht der Tenant
     * schon bereit; nur wenn nicht, wird er geholt. Schlaegt beides fehl
     * (Artisan-Lauf, geloeschtes Portal), bleibt die Meldung trotzdem lesbar.
     */
    private function portal(?int $tenantId): string
    {
        if ($tenantId === null) {
            return __('zentral');
        }

        $aktuell = function_exists('tenancy') && tenancy()->initialized ? tenant() : null;

        if ($aktuell instanceof Tenant && (int) $aktuell->getKey() === $tenantId) {
            return (string) $aktuell->name;
        }

        return (string) (Tenant::query()->find($tenantId)?->name ?? __('Portal :id', ['id' => $tenantId]));
    }
}
